(function(){
  window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});
  const button=document.getElementById('adminLogout');if(!button)return;
  button.addEventListener('click',async()=>{button.disabled=true;const error=document.getElementById('logoutError');if(error)error.textContent='';try{const response=await fetch('api/logout.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:'{}'});const data=await response.json();if(!response.ok||!data.ok)throw new Error();window.location.replace('login.html');}catch{if(error)error.textContent='Unable to log out. Please try again.';button.disabled=false;}});
})();
