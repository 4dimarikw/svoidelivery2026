import fs from 'node:fs/promises';
import {request as nodeRequest} from 'node:http';

// Node fetch may override Host. Native HTTP preserves the actual test header.
async function fetch(url, options) {
  return new Promise((resolve, reject) => {
    const req = nodeRequest(url, options, res => {
      let body = '';
      res.setEncoding('utf8');
      res.on('data', chunk => { body += chunk; });
      res.on('end', () => {
        const headers = new Headers();
        for (let i = 0; i < res.rawHeaders.length; i += 2) headers.append(res.rawHeaders[i], res.rawHeaders[i + 1]);
        resolve({status: res.statusCode, headers, text: async () => body});
      });
    });
    req.on('error', reject);
    req.end(options.body);
  });
}

// Read-only GETs and rejected guest submissions against the authorized local app.
const base = 'http://localhost:8000';
const jar = new Map();
const results = [];
async function request(label, path, {method='GET', data, token, headers={}}={}) {
  const response = await fetch(base + path, {
    method, redirect:'manual', signal:AbortSignal.timeout(20000),
    headers:{Accept:'application/json', Cookie:[...jar].map(([k,v])=>`${k}=${v}`).join('; '),
      ...(data ? {'Content-Type':'application/json'}:{}), ...(token ? {'X-CSRF-TOKEN':token}:{}), ...headers},
    ...(data ? {body:JSON.stringify(data)}:{}),
  });
  const cookies=response.headers.getSetCookie();
  for (const cookie of cookies) { const pair=cookie.split(';')[0]; const i=pair.indexOf('='); jar.set(pair.slice(0,i),pair.slice(i+1)); }
  const body=await response.text();
  let json; try {json=JSON.parse(body);} catch {}
  results.push({label,method,path,status:response.status,location:response.headers.get('location'),
    errorFields:Object.keys(json?.errors??{}),exception:json?.exception??null,
    debugTrace:!!json?.trace,
    rawXssTag:body.includes('<svg onload=alert(7319)>'),
    headers:Object.fromEntries(['content-security-policy','content-security-policy-report-only','x-frame-options','x-content-type-options','referrer-policy','strict-transport-security','access-control-allow-origin','retry-after','x-ratelimit-remaining'].map(k=>[k,response.headers.get(k)])),
    cookies:cookies.map(c=>({name:c.split('=')[0],secure:/;\s*secure/i.test(c),httpOnly:/;\s*httponly/i.test(c),sameSite:c.match(/samesite=([^;]+)/i)?.[1]})),
    exposedEnv:/^APP_KEY=/m.test(body),exposedGit:/\[core\]/.test(body)});
  return body;
}
const login=await request('login form','/login',{headers:{Accept:'text/html'}});
const token=login.match(/name="_token"[^>]*value="([^"]+)"/)?.[1] ?? login.match(/name="csrf-token"[^>]*content="([^"]+)"/)?.[1];
if(!token) throw new Error('CSRF token unavailable');
for(const path of ['/', '/register','/forgot-password','/account/profile','/account/addresses','/account/orders/1','/cart','/.env','/.git/config','/storage/logs/laravel.log']) await request('guest/read access',path);
for(const path of ['/login','/register','/forgot-password','/checkout','/auth/telegram/webapp']) await request('missing CSRF',path,{method:'POST',data:{}});
for(const [label,path] of [
  ['search XSS','/?q='+encodeURIComponent('<svg onload=alert(7319)>')],
  ['search SQLi', '/?q='+encodeURIComponent("' OR 1=1 --")],
  ['search array','/?q[]=x'],
  ['search length bound','/?q='+encodeURIComponent('a'.repeat(256))],
  ['sort SQLi','/?sort='+encodeURIComponent('price desc,(select 1)')],
  ['invalid category element','/?categories[]=x'],
  ['page array','/?page[]=1'],
  ['telegram forged signature','/auth/telegram/callback?id=123456789&auth_date=1&hash=invalid'],
]) await request(label,path);
await request('login invalid credentials','/login',{method:'POST',token,data:{email:'security-audit-20260912@example.invalid',password:'invalid-test-only'}});
await request('login array email','/login',{method:'POST',token,data:{email:['audit'],password:'invalid-test-only'}});
await request('registration array email','/register',{method:'POST',token,data:{email:['audit']}});
await request('reset array email','/forgot-password',{method:'POST',token,data:{email:['audit']}});
await request('reset submission array email','/reset-password',{method:'POST',token,data:{email:['audit']}});
await request('registration missing required fields','/register',{method:'POST',token,data:{email:'security-audit-20260912@example.invalid'}});
await request('forged Telegram webapp','/auth/telegram/webapp',{method:'POST',token,data:{init_data:'auth_date=1&hash=invalid'}});
await request('host header injection','/login',{headers:{Host:'audit.invalid',Accept:'text/html'}});
await request('production ui kit disabled','/ui-kit');
await request('production debugbar disabled','/_debugbar/open');
await fs.writeFile('reports/security-http-fixed-2026-09-12.json', JSON.stringify(results,null,2)+'\n');
console.log(JSON.stringify(results.map(({label,path,status,errorFields,exception,debugTrace,location})=>({label,path,status,errorFields,exception,debugTrace,location})),null,2));
