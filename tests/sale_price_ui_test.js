const {readFileSync}=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const context={window:{BookBridgeAuth:{currentUser:{full_name:'Test Seller'}}},URL,location:{href:'http://localhost/'}};
vm.runInNewContext(readFileSync(require.resolve('../frontend-integration.js'),'utf8'),context);
const UI=context.window.BookBridgeUI;
const React={createElement:(tag,props,...children)=>({tag,props,children})};
const report={summary:{completed_sales_count:3,revenue:450.5,average_order_value:225.25},
  revenue_note:'1 earlier sale has no recorded amount.',transactions:[
    {purchase_request_id:1,sale_price:null,completed_at:'2026-10-01 10:00:00'},
    {purchase_request_id:2,sale_price:0,completed_at:'2026-10-02 10:00:00'},
    {purchase_request_id:3,sale_price:450.5,completed_at:'2026-10-02 11:00:00'}]};
const view=UI.reportView(React,report);
report.summary.active_users=7;
report.summary.activity_note='Unique authenticated accounts since tracking began.';
assert.equal(UI.reportView(React,report).kpis.users,7);
assert.ok(JSON.stringify(UI.reportView(React,report).note).includes('Unique authenticated accounts'));
report.summary.active_users=0;
assert.equal(UI.reportView(React,report).kpis.users,0);
report.summary.active_users=null;
assert.equal(UI.reportView(React,report).kpis.users,'Not recorded');
assert.equal(UI.reportView(React,null).kpis.users,'Loading…');
assert.equal(view.kpis.revenue,'৳450.50');
assert.equal(view.kpis.avg,'৳225.25');
assert.equal(view.transactions[0].price,'Not recorded');
assert.equal(view.transactions[1].price,'৳0.00');
assert.equal(view.transactions[2].price,'৳450.50');
assert.equal(view.data[1].revenue,450.5);
assert.ok(JSON.stringify(view.note).includes('earlier sale'));
assert.ok(JSON.stringify(view.note).includes('450.50'));
const sales=UI.sales([{listing_id:1,price:999,sale_price:450.5},{listing_id:2,price:999,sale_price:null}]);
assert.equal(sales[0].price,'৳450.50','history uses snapshot instead of edited listing price');
assert.equal(sales[1].price,'Not recorded');
assert.equal(UI.reportView(React,null).kpis.revenue,'Loading…');
const history=UI.sales([
  {listing_id:1,purchase_request_id:1,price:999,sale_price:450.5},
  {listing_id:2,purchase_request_id:2,price:999,sale_price:0},
  {listing_id:3,purchase_request_id:3,price:999,sale_price:null},
  {listing_id:4,purchase_request_id:null,price:999,sale_price:null}
]);
const summary=UI.sellerSalesSummary(history);
assert.equal(summary.total,4);
assert.equal(summary.revenue,'৳450.50');
assert.equal(summary.average,'৳225.25','zero is included, unknown and manual sales are excluded');
assert.ok(summary.note.includes('2 sale(s)'));
assert.equal(UI.sellerSalesSummary([history[2]]).revenue,'৳0.00');
assert.equal(UI.sellerSalesSummary([history[2]]).average,'Not recorded');
assert.equal(UI.sellerSalesSummary([]).total,0);
let rendered=JSON.stringify(UI.SalesHistory({React,sellerSales:history,navigate:()=>{}}));
assert.ok(rendered.includes('Recorded Revenue') && rendered.includes('৳450.50') && rendered.includes('৳225.25'));
assert.ok(!rendered.includes('completed sales have no stored transaction price'));
rendered=JSON.stringify(UI.SalesHistory({React,sellerSales:null,navigate:()=>{}}));
assert.ok(rendered.includes('Loading sales history'));
assert.ok(!rendered.includes('৳0.00'),'loading must not appear as zero revenue');
rendered=JSON.stringify(UI.SalesHistory({React,sellerSales:history,loadError:'Network failure',navigate:()=>{},onRetry:()=>{}}));
assert.ok(rendered.includes('Retry') && rendered.includes('Network failure'));
assert.ok(!rendered.includes('৳450.50'),'failed refresh hides stale totals');
assert.match(readFileSync(require.resolve('../frontend-integration.js'),'utf8'),/case 'sales-history':page=h\(SalesHistory,/,'route uses API-connected history component');
console.log('PASS recorded money, zero, historical unknown, daily revenue, coverage note, immutable seller history and loading state');
