const params = new URLSearchParams(window.location.search);
const userId = params.get("id");
const accountType = params.get("type") === "admin" ? "admin" : "user";

function setText(elementId, value) {
    document.getElementById(elementId).textContent = value ?? "-";
}

async function loadUserDetails() {
    if (!userId || !/^[1-9]\d*$/.test(userId)) {
        setText("profileName", "Invalid user ID.");
        return;
    }

    try {
        const response = await fetch(
            `api/view_user.php?id=${encodeURIComponent(userId)}&type=${encodeURIComponent(accountType)}`,
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || `Unable to load account details (${response.status}).`);
        }

        if (!data.success || !data.user) {
            throw new Error(data.message || "User not found.");
        }

        const user = data.user;
        const isAdmin = data.account_type === "admin";

        setText("detailsTitle", isAdmin ? "Admin Details" : "User Details");
        setText("profileName", user.name);
        setText("profileEmail", user.email);
        setText("userId", user.user_id);
        setText("userName", user.name);
        setText("userEmail", user.email);
        setText("createdAt", user.created_at);
        setText("updatedAt", user.updated_at);

        // Account status
        const statusElement = document.getElementById("userStatus");
        const isActive = Number(user.is_active) === 1;

        statusElement.replaceChildren();

        const statusBadge = document.createElement("span");
        statusBadge.className = isActive
            ? "status active"
            : "status inactive";

        statusBadge.textContent = isAdmin
            ? "Administrator"
            : (isActive ? "Active" : "Inactive");
        statusElement.appendChild(statusBadge);

        // User avatar
        const avatarElement = document.getElementById("userAvatar");
        const avatar = user.avatar_url || (isAdmin ? "🛡️" : "🌲");

        avatarElement.replaceChildren();

        const isImage = /^(https?:\/\/|\/uploads\/|uploads\/)/i.test(avatar);

        if (isImage) {
            const img = document.createElement("img");
            img.src = avatar;
            img.alt = "User Avatar";
            avatarElement.appendChild(img);
        } else {
            avatarElement.textContent = avatar;
        }

    } catch (error) {
        setText("profileName", "Unable to load user.");
        setText("profileEmail", error.message);
    }
}

loadUserDetails();
