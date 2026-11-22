const CACHE='eduschool-static-v1';
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.add('/offline.html')).then(()=>self.skipWaiting())));
self.addEventListener('activate',event=>event.waitUntil(self.clients.claim()));
self.addEventListener('fetch',event=>{
  const url=new URL(event.request.url);
  if(event.request.method!=='GET'||url.origin!==self.location.origin||url.pathname.startsWith('/api/')||url.pathname.startsWith('/login')||url.pathname.startsWith('/portal')||url.pathname.startsWith('/students')||url.pathname.startsWith('/payments'))return;
  event.respondWith(fetch(event.request).catch(()=>caches.match('/offline.html')));
});
