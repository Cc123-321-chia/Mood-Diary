/* ============================================================
   diary-crud.js — v20261127
   Removed: drag-to-reorder media.
   Kept: create/edit, upload photos/videos, save, delete,
         fullpage detail, lightbox, duplicate dialog.

   Upload changes vs v20261126:
   - Server upload limits fetched once from api/upload_limits.php
     and used to pre-check files at selection time.
   - uploadPendingMedia batches by count AND cumulative bytes.
     Files larger than SAFE_BATCH_BYTES are sent one per batch.
   - Files that exceed the server single-file limit are rejected
     at selection time with a clear message.
   - Video duration is read via <video> and sent as durations[].
   - A batch that returns ok:true with uploaded:[] while we sent
     files is treated as failure; failed files stay in localFiles
     for retry.
   ============================================================ */
(function () {
  'use strict';

  if (!window.MoodDiary) {
    console.error('dashboard-crud.js: window.MoodDiary missing. Load dashboard-core.js first.');
    return;
  }

  const MD = window.MoodDiary;
  const { $, $$, el, escapeHtml, safeUrl, toast, postJson, todayISO, nowHHMM, formatDateReadable } = MD;

  const API = {
    save:   'api/save_diary.php',
    del:    'api/delete_diary.php',
    upload: 'api/upload_media.php',
    limits: 'api/upload_limits.php',
  };

  const MAX_MEDIA = 9;
  const MAX_IMAGE = 8 * 1024 * 1024;
  const MAX_VIDEO = 40 * 1024 * 1024;

  // Batches are capped by both count and cumulative bytes.
  const BATCH_MAX_FILES = 3;
  const SAFE_BATCH_BYTES = 6 * 1024 * 1024;

  // Server limits, cached after the first fetch.
  let serverLimits = null;
  let serverLimitsPromise = null;

  function loadServerLimits() {
    if (serverLimitsPromise) return serverLimitsPromise;
    serverLimitsPromise = fetch(API.limits, { credentials: 'same-origin' })
      .then(res => res.json())
      .then(data => {
        if (data && data.ok) serverLimits = data;
        return serverLimits;
      })
      .catch(() => {
        // Conservative fallback if the probe fails.
        serverLimits = {
          singleFileMax: 6 * 1024 * 1024,
          maxImageBytes: MAX_IMAGE,
          maxVideoBytes: MAX_VIDEO,
          maxMedia: MAX_MEDIA,
        };
        return serverLimits;
      });
    return serverLimitsPromise;
  }
  // Kick off the probe as soon as this script loads.
  loadServerLimits();

  const fullpageOverlay = document.getElementById('fullpageOverlay');
  const fullpageContent = document.getElementById('fullpageContent');
  const fullpageCloseBtn = document.getElementById('fullpageCloseBtn');

  const editorState = {
    editingId: null,
    removedMediaIds: [],
    localFiles: [],       // { tempId, file, previewUrl, duration }
    existingMedia: [],    // server media rows
    saving: false,
  };

  let editorWired = false;

  // ============================================================
  // Duplicate dialog
  // ============================================================
  function showDuplicateDialog(existingDiaryId) {
    $$('.dup-dialog-backdrop').forEach(n => n.remove());
    const backdrop = el('div', 'dup-dialog-backdrop active');
    backdrop.innerHTML = `
      <div class="dup-dialog" role="dialog" aria-modal="true" aria-labelledby="dupTitle">
        <div class="dup-icon"><i class="fas fa-calendar-check"></i></div>
        <h3 id="dupTitle">You already wrote a diary for this day</h3>
        <p>This day already has a diary entry. You can open it in the editor to add more content or media.</p>
        <div class="dup-dialog-actions">
          <button type="button" data-act="edit" class="primary"><i class="fas fa-pen"></i> Edit that diary</button>
          <button type="button" data-act="cancel"><i class="fas fa-times"></i> Cancel</button>
        </div>
      </div>`;
    document.body.appendChild(backdrop);
    const close = () => backdrop.remove();
    backdrop.addEventListener('click', ev => { if (ev.target === backdrop) close(); });
    backdrop.querySelector('[data-act="cancel"]').addEventListener('click', close);
    backdrop.querySelector('[data-act="edit"]').addEventListener('click', () => {
      close();
      const target = MD.getDiaryById(existingDiaryId);
      if (target) openEditor(target);
      else toast('error', 'Could not locate that diary on this page.');
    });
  }

  // ============================================================
  // Editor
  // ============================================================
  function buildMoodOptions(selectedCode) {
    const list = MD.moodsFromDb;
    if (!list || !list.length) {
      return `<option value="${selectedCode || 'happy'}" selected>${selectedCode || 'happy'}</option>`;
    }
    return list.map(m => {
      const sel = (m.code === selectedCode) ? ' selected' : '';
      return `<option value="${m.code}"${sel}>${m.emoji} ${m.label}</option>`;
    }).join('');
  }
  function buildWeatherOptions(selectedCode) {
    const list = MD.weathersFromDb;
    if (!list || !list.length) {
      return `<option value="${selectedCode || 'sunny'}" selected>${selectedCode || 'sunny'}</option>`;
    }
    return list.map(w => {
      const sel = (w.code === selectedCode) ? ' selected' : '';
      return `<option value="${w.code}"${sel}>${w.emoji} ${w.label}</option>`;
    }).join('');
  }

  function buildEditor() {
    const backdrop = el('div', 'diary-editor-backdrop');
    backdrop.id = 'diaryEditorBackdrop';
    backdrop.innerHTML = `
      <div class="diary-editor-panel" role="dialog" aria-modal="true" aria-labelledby="editorTitle">
        <div class="diary-editor-heading">
          <h2 id="editorTitle">Write Diary</h2>
          <button type="button" id="editorCloseBtn" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <form id="diaryForm" novalidate>
          <div class="diary-form-grid">
            <div class="diary-field">
              <label for="diaryDate">Date</label>
              <input type="date" id="diaryDate" name="date" required>
            </div>
            <div class="diary-field">
              <label for="diaryTime">Time</label>
              <input type="time" id="diaryTime" name="time">
            </div>
            <div class="diary-field">
              <label for="diaryMood">Mood</label>
              <select id="diaryMood" name="moodCode"></select>
            </div>
            <div class="diary-field">
              <label for="diaryWeather">Weather</label>
              <select id="diaryWeather" name="weatherCode"></select>
            </div>
            <div class="diary-field wide">
              <label for="diaryContent">Diary content</label>
              <textarea id="diaryContent" name="content" maxlength="10000" required
                placeholder="What happened today?"></textarea>
            </div>
            <div class="diary-field wide">
              <label>Photos &amp; videos (max ${MAX_MEDIA})</label>
              <div class="diary-upload-zone" id="diaryDropZone">
                <input type="file" id="diaryFileInput"
                  accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" multiple>
                <p>Click to upload photos/videos.</p>
              </div>
              <div class="diary-previews" id="diaryPreviews"></div>
              <div class="diary-editor-error" id="diaryEditorError"></div>
            </div>
          </div>
          <div class="diary-form-actions">
            <button type="button" id="editorCancelBtn">Cancel</button>
            <button type="submit" class="diary-primary" id="editorSaveBtn">
              <i class="fas fa-check"></i> Save
            </button>
          </div>
        </form>
      </div>`;
    document.body.appendChild(backdrop);
    wireEditorEvents(backdrop);
    return backdrop;
  }

  function wireEditorEvents(root) {
    if (editorWired) return;
    editorWired = true;

    $('#editorCloseBtn', root).addEventListener('click', closeEditor);
    $('#editorCancelBtn', root).addEventListener('click', closeEditor);
    root.addEventListener('mousedown', ev => { if (ev.target === root) closeEditor(); });

    const zone = $('#diaryDropZone', root);
    const fileInput = $('#diaryFileInput', root);
    zone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => { handleFiles(root, fileInput.files); fileInput.value = ''; });

    ['dragenter', 'dragover'].forEach(type => {
      zone.addEventListener(type, ev => { ev.preventDefault(); zone.classList.add('drag-over'); });
    });
    ['dragleave', 'drop'].forEach(type => {
      zone.addEventListener(type, ev => { ev.preventDefault(); zone.classList.remove('drag-over'); });
    });
    zone.addEventListener('drop', ev => { handleFiles(root, ev.dataTransfer.files); });

    $('#diaryForm', root).addEventListener('submit', ev => {
      ev.preventDefault();
      submitEditor(root);
    });
  }

  function openEditor(diary) {
    const backdrop = $('#diaryEditorBackdrop') || buildEditor();
    const isEdit = !!diary;

    editorState.editingId = isEdit ? diary.id : null;
    editorState.removedMediaIds = [];
    editorState.localFiles = [];
    editorState.existingMedia = isEdit ? (diary.media || []).slice() : [];
    editorState.saving = false;

    $('#editorTitle', backdrop).textContent = isEdit ? 'Edit Diary' : 'Write Diary';

    const dateInput = $('#diaryDate', backdrop);
    dateInput.value = isEdit ? diary.date : todayISO();
    dateInput.max = todayISO();

    const timeInput = $('#diaryTime', backdrop);
    timeInput.value = isEdit ? (diary.time || '') : nowHHMM();

    const selectedMood = isEdit ? (diary.moodCode || 'happy') : 'happy';
    const selectedWeather = isEdit ? (diary.weatherCode || 'sunny') : 'sunny';
    $('#diaryMood', backdrop).innerHTML    = buildMoodOptions(selectedMood);
    $('#diaryWeather', backdrop).innerHTML = buildWeatherOptions(selectedWeather);

    $('#diaryContent', backdrop).value = isEdit ? (diary.content || '') : '';

    $('#diaryEditorError', backdrop).textContent = '';
    renderPreviews(backdrop);

    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(() => $('#diaryContent', backdrop).focus(), 60);
  }

  function closeEditor() {
    const backdrop = $('#diaryEditorBackdrop');
    if (!backdrop) return;
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
    editorState.localFiles.forEach(f => URL.revokeObjectURL(f.previewUrl));
    editorState.localFiles = [];
    editorState.existingMedia = [];
    editorState.removedMediaIds = [];
    editorState.editingId = null;
    editorState.saving = false;
  }

  // ============================================================
  // Previews (no drag)
  // ============================================================
  function renderPreviews(root) {
    const wrap = $('#diaryPreviews', root);
    wrap.innerHTML = '';

    // Existing media (in DB sort_order).
    editorState.existingMedia
      .slice()
      .sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0))
      .forEach(media => {
        const isVideo = media.type === 'video';
        const card = el('div', 'diary-preview');
        card.dataset.entryKind = 'existing';
        card.dataset.entryId = String(media.id);
        const url = safeUrl(media.url);
        const mediaHtml = isVideo
          ? `<video src="${url}" muted playsinline preload="metadata"></video>`
          : `<img src="${url}" alt="Photo" draggable="false">`;
        card.innerHTML = `
          ${mediaHtml}
          <small>${isVideo ? 'Video' : 'Photo'}</small>
          <button type="button" class="remove-preview" aria-label="Remove"><i class="fas fa-times"></i></button>`;
        card.querySelector('.remove-preview').addEventListener('click', ev => {
          ev.stopPropagation();
          removeExisting(media.id);
          renderPreviews(root);
        });
        wrap.appendChild(card);
      });

    // Local (not yet uploaded) media, in upload order.
    editorState.localFiles.forEach(local => {
      const isVideo = local.file.type.startsWith('video/');
      const card = el('div', 'diary-preview');
      card.dataset.entryKind = 'local';
      card.dataset.entryId = local.tempId;
      const mediaHtml = isVideo
        ? `<video src="${local.previewUrl}" muted playsinline preload="metadata"></video>`
        : `<img src="${local.previewUrl}" alt="Photo" draggable="false">`;
      card.innerHTML = `
        ${mediaHtml}
        <small>${isVideo ? 'Video' : 'Photo'}</small>
        <button type="button" class="remove-preview" aria-label="Remove"><i class="fas fa-times"></i></button>`;
      card.querySelector('.remove-preview').addEventListener('click', ev => {
        ev.stopPropagation();
        removeLocal(local.tempId);
        renderPreviews(root);
      });
      wrap.appendChild(card);
    });
  }

  function removeExisting(mediaId) {
    editorState.existingMedia = editorState.existingMedia.filter(m => m.id !== mediaId);
    editorState.removedMediaIds.push(mediaId);
  }
  function removeLocal(tempId) {
    const idx = editorState.localFiles.findIndex(f => f.tempId === tempId);
    if (idx >= 0) {
      URL.revokeObjectURL(editorState.localFiles[idx].previewUrl);
      editorState.localFiles.splice(idx, 1);
    }
  }

  // ============================================================
  // Read video duration via a temporary <video> element.
  // Resolves to null on failure so callers never block.
  // ============================================================
  function readVideoDuration(file) {
    return new Promise(resolve => {
      const url = URL.createObjectURL(file);
      const v = document.createElement('video');
      v.preload = 'metadata';
      v.muted = true;
      let settled = false;
      const done = (val) => {
        if (settled) return;
        settled = true;
        URL.revokeObjectURL(url);
        resolve(val);
      };
      v.addEventListener('loadedmetadata', () => {
        const d = isFinite(v.duration) && v.duration > 0 ? Math.round(v.duration) : null;
        done(d);
      });
      v.addEventListener('error', () => done(null));
      // Safety timeout: never wait longer than 5s for metadata.
      setTimeout(() => done(null), 5000);
      v.src = url;
    });
  }

  // ============================================================
  // File selection — pre-check against server limits.
  // Large files (> singleFileMax) are rejected here so the user
  // never waits for an upload that will fail.
  // ============================================================
  function handleFiles(root, fileList) {
    const files = Array.from(fileList || []);
    if (!files.length) return;

    const errorBox = $('#diaryEditorError', root);
    errorBox.textContent = '';

    const current = editorState.existingMedia.length + editorState.localFiles.length;
    const remaining = MAX_MEDIA - current;

    if (remaining <= 0) {
      errorBox.textContent = `Maximum ${MAX_MEDIA} media files per diary.`;
      return;
    }

    const toAdd = files.slice(0, remaining);
    const skipped = files.length - toAdd.length;

    const singleFileMax = (serverLimits && serverLimits.singleFileMax) || 0;
    const rejected = [];
    const accepted = [];

    for (const file of toAdd) {
      const isVideo = file.type.startsWith('video/');
      const limit = isVideo ? MAX_VIDEO : MAX_IMAGE;

      if (!/^(image\/(jpeg|png|webp|gif)|video\/(mp4|webm))$/.test(file.type)) {
        rejected.push(`${file.name} (unsupported type)`);
        continue;
      }
      if (file.size > limit) {
        rejected.push(`${file.name} (too large; max ${Math.round(limit / 1024 / 1024)} MB)`);
        continue;
      }
      // Only apply the server ceiling to files that exceed the safe batch size.
      // Small files go in batches and never hit post_max_size.
      if (file.size > SAFE_BATCH_BYTES && singleFileMax > 0 && file.size > singleFileMax) {
        rejected.push(
          `${file.name} (${(file.size / 1024 / 1024).toFixed(1)} MB exceeds server limit ` +
          `${(singleFileMax / 1024 / 1024).toFixed(1)} MB)`
        );
        continue;
      }
      accepted.push(file);
    }

    // Read video durations in parallel, then insert into localFiles.
    const durationPromises = accepted.map(f =>
      f.type.startsWith('video/') ? readVideoDuration(f) : Promise.resolve(null)
    );
    Promise.all(durationPromises).then(durations => {
      accepted.forEach((file, i) => {
        const tempId = `local-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        const previewUrl = URL.createObjectURL(file);
        editorState.localFiles.push({
          tempId,
          file,
          previewUrl,
          duration: durations[i] ?? null,
        });
      });

      const msgs = [];
      if (skipped > 0) msgs.push(`${skipped} file(s) skipped — max ${MAX_MEDIA} per diary.`);
      if (rejected.length) msgs.push(`Rejected: ${rejected.join(', ')}`);
      if (msgs.length) errorBox.textContent = msgs.join(' ');

      renderPreviews(root);
    });
  }

  // ============================================================
  // Batch builder — respect both count and byte caps.
  // A file larger than SAFE_BATCH_BYTES is sent alone.
  // ============================================================
  function buildBatches(files) {
    const batches = [];
    let current = [];
    let currentBytes = 0;

    for (const f of files) {
      const size = f.file.size;

      // Oversized file: flush current batch, then send it alone.
      if (size > SAFE_BATCH_BYTES) {
        if (current.length) {
          batches.push(current);
          current = [];
          currentBytes = 0;
        }
        batches.push([f]);
        continue;
      }

      if (
        current.length >= BATCH_MAX_FILES ||
        currentBytes + size > SAFE_BATCH_BYTES
      ) {
        if (current.length) batches.push(current);
        current = [];
        currentBytes = 0;
      }
      current.push(f);
      currentBytes += size;
    }
    if (current.length) batches.push(current);
    return batches;
  }

  // ============================================================
  // Submit flow
  // ============================================================
  function submitEditor(root) {
    if (editorState.saving) return;

    const errorBox = $('#diaryEditorError', root);
    errorBox.textContent = '';

    const date = $('#diaryDate', root).value;
    const time = $('#diaryTime', root).value;
    const moodCode = $('#diaryMood', root).value;
    const weatherCode = $('#diaryWeather', root).value;
    const content = $('#diaryContent', root).value.trim();

    if (!date) { errorBox.textContent = 'Please pick a date.'; return; }
    if (date > todayISO()) { errorBox.textContent = 'You cannot pick a future date.'; return; }
    if (!content) { errorBox.textContent = 'Diary content is required.'; return; }

    editorState.saving = true;
    const saveBtn = $('#editorSaveBtn', root);
    saveBtn.disabled = true;
    const originalHtml = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    const payload = {
      diaryId: editorState.editingId,
      date, time, moodCode, weatherCode, content,
      removedMediaIds: editorState.removedMediaIds.slice(),
    };

    let uploadWarning = null;

    postJson(API.save, payload)
      .then(({ status, data }) => {
        if (status === 409) {
          closeEditor();
          showDuplicateDialog(data && data.existingDiaryId ? data.existingDiaryId : 0);
          return Promise.reject({ __duplicate: true });
        }
        if (!data || !data.ok) throw new Error((data && data.error) || 'Could not save diary.');
        return data.diaryId;
      })
      .then(diaryId => uploadPendingMedia(diaryId)
        .catch(err => {
          uploadWarning = err;
          console.warn('Upload failed:', err);
        })
        .then(() => diaryId)
      )
      .then(() => {
        if (uploadWarning) {
          const remaining = editorState.localFiles.length;
          toast(
            'error',
            (uploadWarning && uploadWarning.message) || 'Some media failed to upload.'
          );
          if (remaining > 0) {
            errorBox.textContent =
              `Diary saved, but ${remaining} file(s) failed to upload. ` +
              `Close and reopen this diary to retry.`;
            // Keep the editor open so the user can see what's left.
            return;
          }
        }
        toast('success', 'Diary saved.');
        closeEditor();
        MD.loadFromDatabase();
      })
      .catch(err => {
        if (err && err.__duplicate) return;
        console.error('Save flow failed:', err);
        errorBox.textContent = (err && err.message) ? err.message : 'Could not save diary.';
      })
      .finally(() => {
        editorState.saving = false;
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalHtml;
      });
  }

  // ============================================================
  // Upload pending media in sequential batches.
  // Failed batches stop the loop; unsent files stay in localFiles.
  // ============================================================
  async function uploadPendingMedia(diaryId) {
    if (!editorState.localFiles.length) return;

    const batches = buildBatches(editorState.localFiles.slice());

    for (let i = 0; i < batches.length; i++) {
      const batch = batches[i];
      const result = await uploadOneBatch(diaryId, batch);
      // Remove files from localFiles once the server confirmed them.
      const uploadedTempIds = new Set(result.uploadedTempIds);
      editorState.localFiles = editorState.localFiles.filter(
        f => !uploadedTempIds.has(f.tempId)
      );
      // Revoke previews for the ones we sent successfully.
      batch.forEach(f => {
        if (uploadedTempIds.has(f.tempId)) URL.revokeObjectURL(f.previewUrl);
      });

      if (result.rejectedCount > 0) {
        toast(
          'warning',
          `${result.rejectedCount} file(s) were rejected by the server.`
        );
      }
    }
  }

  function uploadOneBatch(diaryId, batch) {
    const form = new FormData();
    form.append('diary_id', String(diaryId));
    batch.forEach((f, idx) => {
      form.append('files[]', f.file, f.file.name);
      // Align durations by index with files[].
      if (f.duration != null) {
        form.append('durations[]', String(f.duration));
      } else {
        form.append('durations[]', '');
      }
    });

    return fetch(API.upload, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': MD.csrfToken },
      body: form,
    }).then(async res => {
      let data = null;
      try { data = await res.json(); } catch (_) {}

      if (!res.ok) {
        const msg = (data && data.error) || ('HTTP ' + res.status);
        throw new Error(msg);
      }
      if (!data || !data.ok) {
        throw new Error((data && data.error) || 'Upload failed.');
      }
      // Guard against silent success: we sent files, server returned none.
      if (batch.length > 0 && (!Array.isArray(data.uploaded) || data.uploaded.length === 0)) {
        throw new Error('Server accepted the request but stored no files.');
      }

      // Map uploaded server rows back to local tempIds by original order.
      // The server preserves request order for successfully accepted files.
      const uploadedTempIds = [];
      let serverIdx = 0;
      const rejected = Array.isArray(data.rejected) ? data.rejected : [];
      const rejectedNames = new Set(
        rejected
          .map(s => String(s).split(' (')[0].trim())
          .filter(Boolean)
      );
      for (const f of batch) {
        if (rejectedNames.has(f.file.name)) continue;
        const row = data.uploaded[serverIdx];
        if (row) {
          uploadedTempIds.push(f.tempId);
          serverIdx++;
        }
      }

      return {
        uploadedTempIds,
        rejectedCount: rejected.length,
      };
    });
  }

  // ============================================================
  // Delete
  // ============================================================
  function deleteDiary(entry) {
    if (!window.confirm('Delete this diary entry? This cannot be undone.')) return Promise.resolve();
    return postJson(API.del, { diaryId: entry.id })
      .then(({ data }) => {
        if (!data || !data.ok) throw new Error((data && data.error) || 'Delete failed.');
        toast('success', 'Diary deleted.');
        closeFullpage();
        MD.loadFromDatabase();
      })
      .catch(err => { toast('error', err.message || 'Delete failed.'); throw err; });
  }

  // ============================================================
  // Detail media
  // ============================================================
  function buildDetailMedia(mediaList) {
    const wrap = el('div', 'detail-media-wrap');
    const media = el('div', 'detail-media');

    if (!mediaList.length) {
      const ph = el('div', 'detail-media-placeholder');
      ph.innerHTML = '<i class="fas fa-feather-alt"></i>';
      media.appendChild(ph);
      wrap.appendChild(media);
      return wrap;
    }

    const track = el('div', 'detail-media-track');
    mediaList.forEach((m, i) => {
      const slide = el('div', 'detail-media-slide');
      if (i === 0) slide.classList.add('active');
      if (m.type === 'video') {
        const v = document.createElement('video');
        v.src = safeUrl(m.url);
        v.controls = true;
        v.playsInline = true;
        v.preload = 'metadata';
        slide.appendChild(v);
      } else {
        const img = document.createElement('img');
        img.src = safeUrl(m.url);
        img.alt = m.alt || 'diary media';
        img.loading = 'lazy';
        img.addEventListener('click', () => openLightbox(safeUrl(m.url)));
        slide.appendChild(img);
      }
      track.appendChild(slide);
    });
    media.appendChild(track);

    if (mediaList.length > 1) {
      const nav = el('div', 'detail-media-nav');
      const prev = el('button', 'detail-media-arrow prev', '<i class="fas fa-chevron-left"></i>');
      const next = el('button', 'detail-media-arrow next', '<i class="fas fa-chevron-right"></i>');
      prev.type = 'button'; next.type = 'button';
      nav.appendChild(prev); nav.appendChild(next);
      media.appendChild(nav);
    }

    wrap.appendChild(media);

    if (mediaList.length > 1) {
      const dotsWrap = el('div', 'detail-media-dots');
      const dots = mediaList.map((m, i) => {
        const d = el('button', 'detail-media-dot');
        d.type = 'button';
        d.setAttribute('aria-label', `Go to item ${i + 1}`);
        if (i === 0) d.classList.add('active');
        d.addEventListener('click', () => go(i));
        dotsWrap.appendChild(d);
        return d;
      });
      wrap.appendChild(dotsWrap);

      let index = 0;
      const slides = $$('.detail-media-slide', track);
      function go(i) {
        index = Math.max(0, Math.min(mediaList.length - 1, i));
        slides.forEach((s, k) => s.classList.toggle('active', k === index));
        dots.forEach((d, k) => d.classList.toggle('active', k === index));
      }
      media.querySelector('.detail-media-arrow.prev').addEventListener('click', () => go(index - 1));
      media.querySelector('.detail-media-arrow.next').addEventListener('click', () => go(index + 1));

      let startX = null;
      media.addEventListener('touchstart', ev => { startX = ev.touches[0].clientX; }, { passive: true });
      media.addEventListener('touchend', ev => {
        if (startX === null) return;
        const dx = ev.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) go(index + (dx < 0 ? 1 : -1));
        startX = null;
      });
    }

    return wrap;
  }

  // ============================================================
  // Lightbox
  // ============================================================
  function openLightbox(url) {
    $$('.media-lightbox').forEach(n => n.remove());
    const lb = el('div', 'media-lightbox');
    lb.innerHTML = `<button type="button" class="media-lightbox-close" aria-label="Close"><i class="fas fa-times"></i></button>`;
    const img = document.createElement('img');
    img.src = url;
    img.alt = 'zoom';
    lb.appendChild(img);
    document.body.appendChild(lb);

    const close = () => lb.remove();
    lb.addEventListener('click', (ev) => {
      if (ev.target === lb || ev.target.closest('.media-lightbox-close')) close();
    });
    document.addEventListener('keydown', function esc(ev) {
      if (ev.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
    });
  }

  // ============================================================
  // Full detail view
  // ============================================================
  function openFullpage(entry) {
    const mediaList = (entry.media || []).slice().sort(
      (a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0)
    );

    $$('.detail-side-actions').forEach(n => n.remove());
    fullpageContent.innerHTML = '';

    const sideActions = el('div', 'detail-side-actions');
    sideActions.innerHTML = `
      <button type="button" class="detail-media-action edit" title="Edit"><i class="fas fa-pen"></i></button>
      <button type="button" class="detail-media-action delete" title="Delete"><i class="fas fa-trash"></i></button>`;
    sideActions.querySelector('.edit').addEventListener('click', () => {
      closeFullpage();
      openEditor(entry);
    });
    sideActions.querySelector('.delete').addEventListener('click', () => {
      deleteDiary(entry);
    });
    fullpageContent.appendChild(sideActions);

    fullpageContent.appendChild(buildDetailMedia(mediaList));

    const body = el('div', 'fullpage-body');
    body.innerHTML = `
      <div class="mw-cards">
        <div class="mw-card">
          <span class="mw-card-label">MOOD</span>
          <div class="mw-card-value">
            <span class="mw-card-emoji">${escapeHtml(entry.mood)}</span>
            <span class="mw-card-text">${escapeHtml(entry.moodLabel || entry.moodCode || '')}</span>
          </div>
        </div>
        <div class="mw-card">
          <span class="mw-card-label">WEATHER</span>
          <div class="mw-card-value">
            <span class="mw-card-emoji">${escapeHtml(entry.weather)}</span>
            <span class="mw-card-text">${escapeHtml(entry.weatherLabel || entry.weatherCode || '')}</span>
          </div>
        </div>
      </div>
      <div class="fullpage-text">${escapeHtml(entry.content)}</div>
      <div class="article-footer">
        <span><i class="far fa-calendar-alt"></i> ${formatDateReadable(entry.date)}</span>
        <span><i class="far fa-clock"></i> ${entry.time || '—'}</span>
      </div>
    `;
    fullpageContent.appendChild(body);

    fullpageOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeFullpage() {
    fullpageOverlay.classList.remove('active');
    document.body.style.overflow = '';
    $$('.detail-side-actions').forEach(n => n.remove());
  }
  fullpageCloseBtn.addEventListener('click', closeFullpage);
  fullpageOverlay.addEventListener('click', (e) => { if (e.target === fullpageOverlay) closeFullpage(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (fullpageOverlay.classList.contains('active')) closeFullpage();
      const editor = $('#diaryEditorBackdrop');
      if (editor && editor.classList.contains('active')) closeEditor();
    }
  });

  MD.openEditor   = openEditor;
  MD.openFullpage = openFullpage;
})();