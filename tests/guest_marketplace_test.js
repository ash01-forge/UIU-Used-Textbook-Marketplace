/* Run with node tests/guest_marketplace_test.js. Uses only in-memory fixtures. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
  constructor() { this.value=''; this.children=[]; this.events={}; }
  get options() { return this.children; }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.children=children; this.value=''; }
  addEventListener(event, handler) { this.events[event]=handler; }
}
const ids=['filtersForm','resetFilters','department','subject','categoryId','perPage','search','itemType','condition','minPrice','maxPrice','sort','direction','filtersError','resultsError','resultsEmpty','pagination','resultsLoading','bookGrid','resultCount'];
const elements=Object.fromEntries(ids.map(id=>[id,new Element()]));
elements.perPage.value='20'; elements.sort.value='created_at'; elements.direction.value='desc';
elements.filtersForm.reset=()=>{for(const id of ['department','subject','categoryId','search','itemType','condition','minPrice','maxPrice'])elements[id].value='';};
const taxonomy={departments:[{id:1,name:'CSE'},{id:2,name:'EEE'}],subjects:[{id:3,name:'Algorithms',departments:['CSE']},{id:4,name:'Circuits',departments:['EEE']},{id:5,name:'Shared',departments:['CSE','EEE']}],categories:[{id:1,name:'CSE',type:'Department'},{id:2,name:'EEE',type:'Department'},{id:3,name:'Algorithms',type:'Subject'},{id:4,name:'Circuits',type:'Subject'},{id:5,name:'Shared',type:'Subject'}]};
const queries=[];
vm.runInNewContext(fs.readFileSync(require.resolve('../guest.js'),'utf8'),{
  document:{querySelector:selector=>elements[selector.slice(1)],createElement:()=>new Element(),createTextNode:text=>text},
  window:{location:{href:'http://localhost/review/browse.html'}},URL,AbortController,setTimeout,clearTimeout,Intl,
  fetch:async url=>{const categories=url.pathname.endsWith('/categories.php');if(!categories)queries.push(url.searchParams);return {ok:true,json:async()=>({success:true,data:categories?taxonomy:{listings:[],total:0,pagination:{page:Number(url.searchParams.get('page')),total_pages:0}}})};}
});
const settle=()=>new Promise(resolve=>setImmediate(resolve));
const options=id=>elements[id].options.map(option=>option.value);
(async()=>{
  await settle();assert.deepEqual(options('categoryId'),['','1','2','3','4','5']);
  elements.categoryId.value='4';elements.subject.value='Circuits';elements.department.value='CSE';elements.department.events.change();await settle();
  assert.deepEqual(options('categoryId'),['','1','3','5']);assert.deepEqual(options('subject'),['','Algorithms','Shared']);
  assert.equal(elements.categoryId.value,'');assert.equal(elements.subject.value,'');assert.equal(queries.at(-1).get('department'),'CSE');assert.equal(queries.at(-1).get('category_id'),null);assert.equal(queries.at(-1).get('page'),'1');
  elements.categoryId.value='3';elements.subject.value='Algorithms';elements.filtersForm.events.submit({preventDefault(){}});await settle();
  assert.equal(queries.at(-1).get('category_id'),'3');assert.equal(queries.at(-1).get('subject'),'Algorithms');
  elements.department.value='EEE';elements.department.events.change();await settle();assert.deepEqual(options('categoryId'),['','2','4','5']);assert.equal(elements.categoryId.value,'');assert.equal(elements.subject.value,'');
  elements.resetFilters.events.click();await settle();assert.deepEqual(options('categoryId'),['','1','2','3','4','5']);assert.equal(queries.at(-1).get('department'),null);assert.equal(queries.at(-1).get('category_id'),null);
  console.log('PASS standalone browse: department categories, shared subjects, stale selection reset, combined query and Reset');
})().catch(error=>{console.error(error);process.exitCode=1;});
