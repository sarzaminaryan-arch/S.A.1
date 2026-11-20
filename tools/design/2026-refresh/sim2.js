const fs=require('fs'),engine=require('php-parser');
const p=new engine({parser:{extractDoc:true},ast:{withPositions:true}});
const code=fs.readFileSync('/home/user/S.A.1/wp-content/themes/sarzaminaryan-child/tests/test-guide-cpt.php','utf8');
const ast=p.parseCode(code,'t');
let all=0, strposn=0;
function walk(n,cb){ if(!n||typeof n!=='object')return; if(Array.isArray(n)){n.forEach(x=>walk(x,cb));return;} if(n.kind)cb(n); for(const k of Object.keys(n)){ if(k==='loc'||k==='leadingComments')continue; walk(n[k],cb);} }
walk(ast,()=>{});
walk(ast,(n)=>{
  if(n.kind!=='call'||!n.what||n.what.name!=='sa_cpt_assert')return;
  all++;
  const src=code.slice(n.arguments[0].loc.start.offset, n.arguments[0].loc.end.offset);
  if(!src.includes('strpos')) console.log(all, '|', src.replace(/\s+/g,' '));
});
console.log('total asserts', all);
