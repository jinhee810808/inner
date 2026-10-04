/* 로그인 후 사용하는 모든 화면에 <script src="js/user_activity.js"></script> 추가 */
(() => {
    if (window.innerActivityStarted) return;
    window.innerActivityStarted = true;
    let busy = false, lastActivity = Date.now(), lastSent = 0;
    const endpoint = new URL('../api/heartbeat.php', document.currentScript.src);
    async function ping() {
        // 보이는 화면에서 최근 5분 내 활동했을 때만 접속 상태를 갱신합니다.
        if (busy || document.hidden || Date.now()-lastActivity>300000 || Date.now()-lastSent<55000) return;
        busy=true; lastSent=Date.now();
        try {
            const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',cache:'no-store'});
            if(response.status===401){ clearInterval(timer); }
            else if(!response.ok) console.warn('접속 상태 갱신 실패:',response.status);
        } catch(error){ console.warn('접속 상태 갱신 실패',error); }
        finally { busy=false; }
    }
    ['pointerdown','keydown','scroll','pointermove','touchstart'].forEach(type=>
        window.addEventListener(type,()=>{lastActivity=Date.now();ping();},{passive:true}));
    document.addEventListener('visibilitychange',()=>{if(!document.hidden){lastActivity=Date.now();ping();}});
    const timer=setInterval(ping,60000);
    ping();
})();
