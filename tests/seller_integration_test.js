/* Run with node tests/seller_integration_test.js. No database or network access. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require.resolve('../frontend-integration.js'), 'utf8');
const events = [], revoked = [];
const location = {href:'http://localhost/review/', hash:'#edit-listing/104'};
const row = {id:104,title:'Discrete Mathematics',author:'Oscar Levin',edition:'4th',department:'CSE',
  course_code:'CSE-221',subject:'DM',item_type:'Textbook',condition_type:'Like New',price:450,
  description:'Original book description',image_url:'uploads/listings/original.png',status:'available'};
const api = {};
class BrowserURL extends URL {
  static createObjectURL(file) {return 'blob:'+file.name;}
  static revokeObjectURL(url) {revoked.push(url);}
}
const context = {URL:BrowserURL,URLSearchParams,setTimeout,clearTimeout,location,
  history:{replaceState(_state,_title,hash){location.hash=hash;}},
  sessionStorage:{setItem(){},removeItem(){},getItem(){return null;}},
  document:{dispatchEvent(event){events.push(event.detail);},addEventListener(){},removeEventListener(){}},
  CustomEvent:class {constructor(type,options){this.type=type;this.detail=options.detail;}},
  window:{BookBridgeAPI:api,BookBridgeAuth:{ready:Promise.resolve(),currentUser:{id:7,role:'seller',full_name:'Seller'}},
    scrollTo(){},addEventListener(){},removeEventListener(){}}};
vm.runInNewContext(source,context);
const UI=context.window.BookBridgeUI;
function harness(render) {
  const slots=[],effects=[];let index=0,dirty=false,value;
  const React={
    createElement:(type,props,...children)=>({type,props:props||{},children:children.flat(Infinity)}),
    useState(initial){const id=index++;if(!(id in slots))slots[id]=initial;return [slots[id],next=>{slots[id]=typeof next==='function'?next(slots[id]):next;dirty=true;}];},
    useRef(initial){const id=index++;if(!(id in slots))slots[id]={current:initial};return slots[id];},
    useEffect(fn,deps){const id=index++,previous=slots[id];if(!previous||deps.some((dep,i)=>dep!==previous.deps[i])){
      slots[id]={deps,cleanup:previous?.cleanup};effects.push(()=>{slots[id].cleanup?.();slots[id].cleanup=fn();});}}
  };
  return {render(){let attempts=0;do{dirty=false;index=0;value=render(React);while(effects.length)effects.shift()();assert.ok(++attempts<20,'render converges');}while(dirty);return value;},
    async settle(){for(let i=0;i<5;i++){await new Promise(resolve=>setImmediate(resolve));this.render();}return value;},
    close(){slots.forEach(slot=>slot?.cleanup?.());}};
}
function find(tree,predicate) {
  if(!tree||typeof tree!=='object')return null;
  if(predicate(tree))return tree;
  for(const child of tree.children||[]){const result=find(child,predicate);if(result)return result;}
  return null;
}
const form=tree=>find(tree,node=>node.type==='form');
const fileInput=tree=>find(tree,node=>node.type==='input'&&node.props.type==='file');
const preview=tree=>find(tree,node=>node.type==='img');
const submit=tree=>form(tree).props.onSubmit({preventDefault(){}});
const deferred=()=>{let resolve;const promise=new Promise(done=>{resolve=done;});return {promise,resolve};};
(async()=>{
  for(const name of ['EditListing','useMarketplaceFilters','marketplaceFilters','marketplacePagination','useMarketplace'])assert.equal(typeof UI[name],'function',name+' exported');
  const names=[...source.matchAll(/\bfunction\s+(\w+)\s*\(/g)].map(match=>match[1]);
  assert.equal(names.filter(name=>name==='EditListing').length,1);
  assert.equal(names.filter(name=>name==='useMarketplace').length,1);
  assert.ok(!/^(<<<<<<<|=======|>>>>>>>)/m.test(source));
  let saved=0,uploads=0,bodies=[],destinations=[];
  api.uploadImage=async()=>{uploads++;return {data:{image_url:'uploads/listings/replacement.png'}};};
  api.editListing=async body=>{bodies.push(body);return {data:{listing:{...row,...body}}};};
  const edit=harness(React=>UI.EditListing({React,listing:UI.listing(row),taxonomy:{departments:[{name:'CSE'}]},
    navigate:value=>destinations.push(value),onSaveListing:()=>saved++}));
  let tree=edit.render();assert.ok(preview(tree).props.src.endsWith('/uploads/listings/original.png'));
  await submit(tree);assert.equal(uploads,0);assert.equal(bodies[0].listing_id,104);assert.equal(bodies[0].price,450);
  assert.equal(Object.hasOwn(bodies[0],'image_url'),false,'no file preserves current cover');assert.equal(saved,1);
  let file={name:'cover.png',type:'image/png',size:100};fileInput(edit.render()).props.onChange({target:{files:[file]}});
  tree=edit.render();assert.equal(preview(tree).props.src,'blob:cover.png');
  api.editListing=async body=>{bodies.push(body);throw Error('Save failed');};
  await submit(tree);assert.equal(uploads,1);assert.equal(saved,1);assert.equal(events.at(-1).error,true);
  api.editListing=async body=>{bodies.push(body);return {data:{listing:{...row,...body,status:'pending_approval'}}};};
  await submit(edit.render());assert.equal(uploads,1,'successful upload cached across failed save and retry');
  assert.equal(bodies.at(-1).image_url,'uploads/listings/replacement.png');assert.equal(saved,2);
  assert.equal(destinations.at(-1),'manage-listings');assert.ok(events.at(-1).message.includes('resubmitted'));
  const waiting=deferred();let attempts=0;
  api.editListing=async body=>{attempts++;await waiting.promise;return {data:{listing:{...row,...body}}};};
  tree=edit.render();const first=submit(tree);await submit(tree);assert.equal(attempts,1,'duplicate submit locked');
  assert.equal(find(edit.render(),node=>node.props.type==='submit').props.disabled,true);
  waiting.resolve();await first;assert.equal(find(edit.render(),node=>node.props.type==='submit').props.disabled,false);
  fileInput(edit.render()).props.onChange({target:{files:[{name:'other.webp',type:'image/webp',size:100}]}});
  tree=edit.render();assert.ok(revoked.includes('blob:cover.png'));await submit(tree);assert.equal(uploads,2,'different file uploaded again');
  edit.close();assert.ok(revoked.includes('blob:other.webp'));
  console.log('PASS EditListing: optional cover, preview cleanup, retry cache, changed file, duplicate submit lock, saved callback and navigation');

  api.allListings=async()=>[];api.categories=async()=>({data:{departments:[],subjects:[],categories:[]}});
  api.sellerListings=async()=>[row];api.sellerSales=async()=>[];
  const components=new Proxy({}, {get:(_object,name)=>'Compiled:'+name});
  const route=harness(React=>UI.render(React,components));route.render();tree=await route.settle();
  const routed=find(tree,node=>node.type===UI.EditListing);assert.ok(routed,'edit-listing route uses editable component');
  assert.equal(routed.props.listing.id,'104');assert.ok(routed.props.React);
  assert.ok(!find(tree,node=>node.type==='Compiled:EditListing'));route.close();
  console.log('PASS edit-listing deep link restores owned listing and uses EditListing with React');

  api.sellerRequests=async()=>({data:{requests:[{id:31,book_title:'Book',buyer_name:'Buyer',status:'accepted',can_complete:true}]}});
  let declined;
  api.sellerRequestAction=async(id,action)=>{declined={id,action};return {success:true};};
  const requests=harness(React=>UI.Requests({React,role:'seller',navigate(){},onRefresh(){},onReview(){},onChat(){}}));
  requests.render();tree=await requests.settle();
  const decline=find(tree,node=>node.type==='button'&&node.children.includes('Decline'));assert.ok(decline);
  await decline.props.onClick();assert.deepEqual(declined,{id:31,action:'decline'});requests.close();
  console.log('PASS accepted purchase request decline uses sellerRequestAction');
})().catch(error=>{console.error(error);process.exitCode=1;});
