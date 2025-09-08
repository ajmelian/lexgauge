document.addEventListener('DOMContentLoaded',function(){
 const consent=document.querySelector('#consentCheckbox'); const btn=document.querySelector('#btnProceed');
 if(consent&&btn){ const t=()=>{btn.disabled=!consent.checked}; consent.addEventListener('change',t); t(); }
 const aiToggle=document.querySelector('#useAi'); const aiBlock=document.querySelector('#aiBlock');
 if(aiToggle&&aiBlock){ const t=()=>aiBlock.style.display=aiToggle.checked?'block':'none'; aiToggle.addEventListener('change',t); t(); }
});
