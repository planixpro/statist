(function(){

if(window.__STATIST_TRACKER__) return;
window.__STATIST_TRACKER__ = true;

const ENDPOINT = "https://your-statist-domain.com/";

const SESSION_KEY = "statist_sid";

function uuid(){
return crypto.randomUUID();
}

let sid;

try{

sid = localStorage.getItem(SESSION_KEY);

if(!sid){
sid = uuid();
localStorage.setItem(SESSION_KEY,sid);
}

}catch(e){

sid = uuid();

}

function send(event,extra={}){

const payload = {

js:1,

ev:event,

sid:sid,

h:location.hostname,

p:location.pathname,

query:location.search,

r:document.referrer,

s:window.innerWidth+"x"+window.innerHeight,

l:navigator.language,

tz:Intl.DateTimeFormat().resolvedOptions().timeZone,

...extra

};

fetch(ENDPOINT,{

method:"POST",

headers:{
"Content-Type":"application/json"
},

body:JSON.stringify(payload),

keepalive:true

}).catch(()=>{});

}

/* page view */

send("page_view");

/* heartbeat */

setTimeout(()=>{

send("heartbeat");

},5000);

/* clicks */

document.addEventListener("click",e=>{

const el = e.target.closest("[data-statist-click]");

if(!el) return;

send("click",{
target:el.getAttribute("data-statist-click")
});

});

/* session end */

document.addEventListener("visibilitychange",()=>{

if(document.visibilityState==="hidden"){

send("session_end");

}

});

})();