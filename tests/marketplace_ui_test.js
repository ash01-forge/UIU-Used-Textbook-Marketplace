/* Run with node tests/marketplace_ui_test.js. No database access. */
const {readFileSync}=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const calls=[];
let apiLoad=async query=>{const params=new URLSearchParams(query);calls.push(params);return {data:{listings:[{id:params.get('page'),title:'Fixture',price:100}],pagination:{total:25,total_pages:3}}};};
const context={window:{BookBridgeAPI:{listings:query=>apiLoad(query),allListings:async()=>Array.from({length:25},(_,id)=>({id,seller_rating:id,price:100}))}},URL,URLSearchParams,location:{href:'http://localhost/'},setTimeout:fn=>setTimeout(fn,0),clearTimeout};
vm.runInNewContext(readFileSync(require.resolve('../frontend-integration.js'),'utf8'),context);
const UI=context.window.BookBridgeUI;
function harness(render) {
  const slots=[],effects=[];let index=0,dirty=false,value;
  const React={useState(initial){const id=index++;if(!(id in slots))slots[id]=initial;return [slots[id],next=>{slots[id]=typeof next==='function'?next(slots[id]):next;dirty=true;}];},useEffect(fn,deps){const id=index++,prev=slots[id];if(!prev||deps.some((dep,i)=>dep!==prev.deps[i])){slots[id]={deps,cleanup:prev?.cleanup};effects.push(()=>{slots[id].cleanup?.();slots[id].cleanup=fn();});}},createElement:(tag,props,...children)=>({tag,props:props||{},children})};
  return {render(){let attempts=0;do{dirty=false;index=0;value=render(React);assert.ok(++attempts<10,'render converges');}while(dirty);while(effects.length)effects.shift()();return value;},async settle(){await new Promise(resolve=>setTimeout(resolve,15));return this.render();},close(){slots.forEach(slot=>slot?.cleanup?.());}};
}
(async()=>{
 let filters={search:'',department:'CSE',type:'All',condition:'All',sort:'price-asc',subject:'Algorithms',category_id:'7',min_price:'0',max_price:'500'};
 const hook=harness(React=>UI.useMarketplace(React,filters));hook.render();let state=await hook.settle();
 for(const [key,value] of Object.entries({department:'CSE',subject:'Algorithms',category_id:'7',min_price:'0',max_price:'500',page:'1',per_page:'12',sort:'price',direction:'asc'}))assert.equal(calls.at(-1).get(key),value);
 assert.equal(state.total,25);state.setPage(2);hook.render();state=await hook.settle();assert.equal(calls.at(-1).get('page'),'2');
 filters={...filters,min_price:'100'};state=hook.render();assert.equal(state.page,1);assert.equal(state.rows.length,0);state=await hook.settle();assert.equal(calls.at(-1).get('min_price'),'100');
 let release;apiLoad=()=>new Promise(resolve=>{release=resolve;});filters={...filters,subject:'Slow'};hook.render();await new Promise(resolve=>setTimeout(resolve,10));
 apiLoad=async()=>({data:{listings:[{id:99,price:200}],pagination:{total:1,total_pages:1}}});filters={...filters,subject:'Latest'};hook.render();state=await hook.settle();assert.equal(state.rows[0].id,'99');
 release({data:{listings:[{id:1,price:100}],pagination:{total:1,total_pages:1}}});state=await hook.settle();assert.equal(state.rows[0].id,'99','stale response ignored');
 apiLoad=async()=>{throw Error('Unavailable');};filters={...filters,subject:'Error'};hook.render();state=await hook.settle();assert.equal(state.error,'Unavailable');assert.equal(state.rows.length,0);
 filters={...filters,sort:'rating'};hook.render();state=await hook.settle();assert.equal(state.rows.length,12);assert.equal(state.rows[0].id,'24');state.setPage(2);hook.render();state=await hook.settle();assert.equal(state.rows[0].id,'12','global rating sorting before pagination');hook.close();
 let department='CSE';const dependent=harness(React=>UI.useMarketplaceFilters(React,department));let extra=dependent.render();extra.set('subject','Algorithms');extra=dependent.render();extra.set('category_id','7');extra=dependent.render();extra.set('min_price','0');extra=dependent.render();department='EEE';extra=dependent.render();assert.equal(extra.subject,'');assert.equal(extra.category_id,'');assert.equal(extra.min_price,'0');extra.clear();assert.equal(dependent.render().min_price,'');
 const tree=UI.marketplaceFilters({createElement:(tag,props,...children)=>({tag,props,children})},{...extra,department:'CSE'},{subjects:[{id:7,name:'Algorithms',departments:['CSE']},{id:8,name:'Circuits',departments:['EEE']}],categories:[{id:1,name:'CSE',type:'Department'},{id:2,name:'EEE',type:'Department'},{id:7,name:'Algorithms',type:'Subject'},{id:8,name:'Circuits',type:'Subject'}]});
 assert.ok(JSON.stringify(tree).includes('Algorithms'));assert.ok(!JSON.stringify(tree).includes('Circuits'));assert.ok(!JSON.stringify(tree).includes('EEE'));
 console.log('PASS query parameters, zero-price bound, pagination, filter reset, stale responses, API errors, global rating order, department dependency reset and taxonomy filtering');
})().catch(error=>{console.error(error);process.exitCode=1;});
