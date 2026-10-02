/* Editable integration logic. The original React layouts remain in app.js. */
(() => {
  'use strict';
  const API = () => window.BookBridgeAPI;
  const pending = new Set();
  const noticeKey = 'bookbridge:error-notice';
  function saveError(notice) {
    try {
      if (notice?.error) sessionStorage.setItem(noticeKey, JSON.stringify({...notice, userId:window.BookBridgeAuth?.currentUser?.id ?? null}));
      else sessionStorage.removeItem(noticeKey);
    } catch { /* The visible notice still works when browser storage is unavailable. */ }
  }
  function restoredError() {
    try {
      const notice=JSON.parse(sessionStorage.getItem(noticeKey)||'null');
      return notice?.error && notice.userId===(window.BookBridgeAuth?.currentUser?.id ?? null) ? notice : null;
    } catch {return null;}
  }
  function notify(message, error = false) {
    saveError({message,error});
    document.dispatchEvent(new CustomEvent('bookbridge:notice', {detail: {message, error}}));
  }
  function imageUrl(value) {
    if (!value) return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="300"%3E%3Crect width="400" height="300" fill="%23e8f0f8"/%3E%3C/svg%3E';
    try {const url = new URL(value, location.href); return ['http:', 'https:'].includes(url.protocol) ? url.href : '';}
    catch {return '';}
  }
  function listing(row, defaultStatus = 'available') {
    const status = row.status || row.listing_status || defaultStatus;
    return {...row, id: String(row.id ?? row.listing_id), sellerId: row.seller_id == null ? null : Number(row.seller_id),
      title: row.title || row.book_title || '', courseCode: row.course_code || '', type: row.item_type || '', condition: row.condition_type || 'Good',
      price: Number(row.price), subject:row.subject||'', department:row.department||'', author:row.author||'', edition:row.edition||'', description: row.description || '', seller: row.seller_name || '',
      sellerRating: row.seller_rating == null ? null : Number(row.seller_rating), sellerReviews: row.review_count ?? null,
      sellerAvatar: (row.seller_name || '').split(/\s+/).map(word => word[0] || '').join('').slice(0,2).toUpperCase(),
      image: imageUrl(row.image_url), postedDate: row.created_at || '', status,
      approved: ['available', 'sold'].includes(status)};
  }
  function sales(rows) {
    return rows.map(row => ({...row, id: `${row.listing_id}-${row.purchase_request_id || 'manual'}`,
      book: row.title, buyer: row.buyer_name || 'Unavailable', seller: window.BookBridgeAuth.currentUser?.full_name || '',
      price: 'Unavailable', date: row.completed_at || row.sold_at, courseCode: row.course_code}));
  }
  async function perform(key, action) {
    if (pending.has(key)) return null;
    pending.add(key);
    try {return await action();}
    catch(error) {notify(error.message || 'Request failed. Please try again.', true); return null;}
    finally {pending.delete(key);}
  }
  function useData(React, load, dependencies = []) {
    const [data, setData] = React.useState(null);
    React.useEffect(() => {let active = true; setData(null);
      load().then(result => {if(active) setData(result.data);}).catch(error => {if(active) notify(error.message, true);});
      return () => {active = false;};
    }, dependencies);
    return data;
  }
  async function upload(file) {
    if (!file) return '';
    if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 2*1024*1024) throw new Error('Choose a JPG, PNG or WebP image up to 2 MB.');
    return (await API().uploadImage(file)).data.image_url;
  }
  function date(value) {return value ? new Date(value).toLocaleDateString('en-GB') : 'Unavailable';}
  function usePreview(React,file,url) {
    const [preview,setPreview]=React.useState('');
    React.useEffect(()=>{if(!file){setPreview('');return;}const local=URL.createObjectURL(file);setPreview(local);return()=>URL.revokeObjectURL(local);},[file]);
    return preview||url;
  }
  function Requests({React,role,navigate,onRefresh,onReview,onChat}) {
    const h=React.createElement, [rows,setRows]=React.useState(null), [busy,setBusy]=React.useState(false), [loadError,setLoadError]=React.useState(null), lock=React.useRef(false);
    async function load() {
      setRows(null);setLoadError(null);
      try {const result=await (role==='seller'?API().sellerRequests():API().purchaseRequests());setRows(result.data.requests);}
      catch(error){setLoadError(error.message||'Could not load purchase requests.');throw error;}
    }
    React.useEffect(()=>{let active=true;setRows(null);setLoadError(null);(role==='seller'?API().sellerRequests():API().purchaseRequests()).then(result=>{if(active)setRows(result.data.requests);}).catch(error=>{if(active){setLoadError(error.message||'Could not load purchase requests.');notify(error.message,true);}});return()=>{active=false;};},[role]);
    async function retry() {if(lock.current)return;lock.current=true;setBusy(true);try{await load();}catch(error){notify(error.message,true);}finally{lock.current=false;setBusy(false);}}
    async function action(row,type) {if(lock.current)return;lock.current=true;setBusy(true);let updated=false;try {
      await (role==='seller'?API().sellerRequestAction(row.id,type):API().cancelPurchaseRequest(row.id));
      updated=true;setRows(null);onRefresh();await load();notify('Purchase request updated.');
    }catch(error){notify(updated?`Purchase request updated, but the latest requests could not be loaded. Use Retry. ${error.message}`:error.message,true);}finally{lock.current=false;setBusy(false);}}
    const button=(label,click)=>h('button',{type:'button',className:'btn-secondary',disabled:busy,onClick:click},label);
    return h('section',{className:'bb-panel'},h('button',{className:'btn-secondary',onClick:()=>navigate(role+'-dashboard')},'Back to dashboard'),h('h1',null,'Purchase Requests'),
      loadError?h('div',null,h('p',{role:'alert'},loadError),button('Retry',retry)):
      rows===null?h('p',{role:'status'},'Loading requests…'):rows.length===0?h('p',null,'No purchase requests yet.'):
      h('div',{className:'bb-requests'},rows.map(row=>h('article',{className:'card',key:row.id},h('h2',null,row.book_title),
        h('p',null,`${role==='seller'?row.buyer_name:row.seller_name} · ${row.status}`),
        h('p',null,`${row.meeting_location || 'Meetup location unavailable'} · ${row.preferred_date || 'Date unavailable'} · Cash on Meet`),
        row.note&&h('p',null,row.note),h('div',{className:'bb-actions'},
          role==='seller'&&row.can_accept&&button('Accept',()=>action(row,'accept')),
          role==='seller'&&['pending','accepted'].includes(row.status)&&button('Decline',()=>action(row,'decline')),
          role==='seller'&&row.can_complete&&button('Complete meetup',()=>action(row,'complete')),
          role==='buyer'&&row.can_cancel&&button('Cancel request',()=>action(row,'cancel')),
          role==='buyer'&&row.can_review&&button('Review seller',()=>onReview(row)),
          button('Messages',()=>onChat(row)))))));
  }
  function Profile({React,role,navigate,onSaved}) {
    const h=React.createElement,[form,setForm]=React.useState(null),[busy,setBusy]=React.useState(false),lock=React.useRef(false);
    React.useEffect(()=>{let active=true;API().profile(role).then(result=>{if(active)setForm(result.data.profile||result.data.user);}).catch(error=>notify(error.message,true));return()=>{active=false;};},[role]);
    async function save(event) {event.preventDefault();if(lock.current)return;lock.current=true;setBusy(true);try {
      const body={full_name:form.full_name,phone:form.phone||null,avatar_url:form.avatar_url||null};
      if(role==='buyer')body.student_id=form.student_id||null;
      await API().updateProfile(role,body);await onSaved();notify('Profile updated.');
    }catch(error){notify(error.message,true);}finally{lock.current=false;setBusy(false);}}
    return h('section',{className:'bb-panel'},h('button',{className:'btn-secondary',onClick:()=>navigate(role+'-dashboard')},'Back to dashboard'),h('h1',null,'My Profile'),
      !form?h('p',{role:'status'},'Loading profile…'):h('form',{className:'card bb-form',onSubmit:save},
        h('p',null,form.email),...['full_name','phone',...(role==='buyer'?['student_id']:[])].map(key=>h('label',{key},key.replace(/_/g,' '),h('input',{className:'input-field',required:key==='full_name',value:form[key]||'',onChange:event=>setForm({...form,[key]:event.target.value})}))),
        h('button',{type:'submit',className:'btn-primary',disabled:busy},busy?'Saving…':'Save profile')));
  }
  function useChat(React,initialPartner,initialName,initialListing,initialTitle) {
    const [conversations,setConversations]=React.useState([]),[partner,setPartner]=React.useState(initialPartner||null),[name,setName]=React.useState(initialName||'Select a conversation'),[listingId,setListingId]=React.useState(initialListing||null),[title,setTitle]=React.useState(initialTitle||''),[messages,setMessages]=React.useState([]),[text,setText]=React.useState(''),[search,setSearch]=React.useState(''),[busy,setBusy]=React.useState(false),[loading,setLoading]=React.useState(false),lock=React.useRef(false);
    const mapMessage=row=>({id:row.id,sender:row.is_me||Number(row.sender_id)===Number(window.BookBridgeAuth.currentUser?.id)?'me':'other',text:row.message_text,time:new Date(row.created_at).toLocaleString('en-GB')});
    const currentPartner=React.useRef(partner);currentPartner.current=partner;
    const choose=row=>{currentPartner.current=row.partner_id;setPartner(row.partner_id);setName(row.partner_name);setListingId(row.listing_id);setTitle(row.listing_title||'');setText('');};
    React.useEffect(()=>{let active=true;API().conversations().then(result=>{if(!active)return;setConversations(result.data.conversations);if(!initialPartner&&result.data.conversations.length)choose(result.data.conversations[0]);}).catch(error=>notify(error.message,true));return()=>{active=false;};},[]);
    React.useEffect(()=>{if(!partner)return;let active=true;setMessages([]);setLoading(true);API().thread(partner).then(result=>{if(active){setName(result.data.partner.full_name);setMessages(result.data.messages.map(mapMessage));setConversations(rows=>rows.map(row=>row.partner_id===partner?{...row,unread_count:0}:row));}}).catch(error=>{if(active)notify(error.message,true);}).finally(()=>{if(active)setLoading(false);});return()=>{active=false;};},[partner]);
    async function send(){if(lock.current||!partner||!text.trim())return;lock.current=true;setBusy(true);const destination=partner,message=text.trim();try {
      const result=await API().sendMessage({receiver_id:Number(destination),message_text:message,listing_id:listingId?Number(listingId):null});
      if(destination===currentPartner.current){setMessages(rows=>[...rows,mapMessage(result.data.message)]);setText('');}
      const updated=await API().conversations();setConversations(updated.data.conversations);
    }catch(error){notify(error.message,true);}finally{lock.current=false;setBusy(false);}}
    return {messages,text,setText,search,setSearch,send,busy,loading,partner,name,title,choose,conversations:conversations.filter(row=>row.partner_name.toLowerCase().includes(search.toLowerCase())).map(row=>({...row,name:row.partner_name,last:row.last_message,time:date(row.last_message_time),unread:row.unread_count,active:row.partner_id===partner}))};
  }
  function useMarketplace(React,filters) {
    const [state,setState]=React.useState({rows:[],loading:true,error:null});
    const {search,department,type,condition,sort}=filters;
    React.useEffect(()=>{
      let active=true;setState({rows:[],loading:true,error:null});
      const timer=setTimeout(async()=>{
        const params=new URLSearchParams();
        if(search.trim())params.set('search',search.trim());
        for(const [key,value] of Object.entries({department,type,condition}))if(value!=='All')params.set(key,value);
        params.set('sort',sort==='price-asc'||sort==='price-desc'?'price':'created_at');
        params.set('direction',sort==='price-asc'?'asc':'desc');
        try {
          const rows=await API().allListings('?'+params.toString());
          if(active)setState({rows:rows.map(row=>listing(row)).filter(row=>row.status==='available'),loading:false,error:null});
        }catch(error){if(active){setState({rows:[],loading:false,error:error.message});notify(error.message,true);}}
      },search?250:0);
      return()=>{active=false;clearTimeout(timer);};
    },[search,department,type,condition,sort,filters.revision]);
    return state;
  }
  function useReport(React,period) {
    const [report,setReport]=React.useState(null);
    React.useEffect(()=>{let active=true;setReport(null);const now=new Date(),from=new Date(now);if(period==='weekly')from.setDate(from.getDate()-6);else if(period==='monthly')from.setDate(1);else {from.setMonth(0);from.setDate(1);}
      const format=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
      (async()=>{let first,transactions=[];for(let page=1;;page++){const result=await API().adminSalesReport(`?from=${format(from)}&to=${format(now)}&per_page=100&page=${page}`);first ||=result.data;transactions.push(...result.data.transactions);if(page>=(result.data.pagination?.total_pages||1))break;}if(active)setReport({...first,transactions});})().catch(error=>notify(error.message,true));return()=>{active=false;};
    },[period]);
    const grouped={};for(const row of report?.transactions||[]){const key=(row.completed_at||'').slice(0,10);grouped[key]=(grouped[key]||0)+1;}
    return {chartTitle:'Completed sales',data:Object.entries(grouped).sort().map(([label,sales])=>({label,sales})),kpis:{sales:report?.summary?.completed_sales_count??'Unavailable',revenue:'Unavailable',avg:'Unavailable',users:'Unavailable'},changes:{sales:'',revenue:'',avg:'',users:''},comparison:'',note:report?.revenue_note||'Unavailable: completed transactions have no stored sale price.',transactions:(report?.transactions||[]).map(row=>({id:row.purchase_request_id,book:row.listing_title,buyer:row.buyer_name,seller:row.seller_name,courseCode:row.course_code||'Unavailable',price:'Unavailable',date:row.completed_at}))};
  }
  function extraLinks(React,role,navigate) {return ['buyer','seller'].includes(role)?React.createElement(React.Fragment,null,...[['purchase-requests','Purchase Requests'],['chat','Messages'],['profile','My Profile']].map(([view,label])=>React.createElement('button',{key:view,type:'button',className:'bb-menu-link',onClick:()=>navigate(view)},label))):null;}
  function EditListing({React, listing:row, taxonomy, navigate, onSaveListing}) {
    const h=React.createElement;
    const [form,setForm]=React.useState({title:row.title,author:row.author||'',edition:row.edition||'',department:row.department,
      course_code:row.courseCode,subject:row.subject||'',item_type:row.type,condition_type:row.condition,price:String(row.price),description:row.description});
    const [file,setFile]=React.useState(null),[busy,setBusy]=React.useState(false),lock=React.useRef(false),uploaded=React.useRef(null);
    const preview=usePreview(React,file,row.image);
    const change=(key,value)=>setForm(current=>({...current,[key]:value}));
    async function save(event) {
      event.preventDefault();if(lock.current)return;lock.current=true;setBusy(true);
      try {
        const body={listing_id:Number(row.id),...form,price:Number(form.price)};
        for(const key of ['title','course_code','subject','description'])body[key]=body[key].trim();
        for(const key of ['author','edition'])body[key]=body[key].trim()||null;
        if(file) {
          // Reuse this upload if saving fails, instead of uploading it again on retry.
          if(uploaded.current?.file!==file)uploaded.current={file,url:await upload(file)};
          body.image_url=uploaded.current.url;
        }
        const result=await API().editListing(body);
        onSaveListing(listing(result.data.listing));
        notify(result.data.listing.status==='pending_approval'?'Listing updated and resubmitted for admin approval.':'Listing updated.');
        navigate('manage-listings');
      }catch(error){notify(error.message,true);}finally{lock.current=false;setBusy(false);}
    }
    const field=(key,label,options={})=>h('label',{key},label,h('input',{className:'input-field',value:form[key],disabled:busy,
      onChange:event=>change(key,event.target.value),...options}));
    const select=(key,label,values)=>h('label',{key},label,h('select',{className:'input-field',value:form[key],disabled:busy,
      onChange:event=>change(key,event.target.value)},values.map(value=>h('option',{key:value,value},value))));
    return h('section',{className:'bb-panel',style:{maxWidth:760,margin:'32px auto',padding:'0 24px 40px'}},
      h('button',{type:'button',className:'btn-secondary',disabled:busy,onClick:()=>navigate('manage-listings')},'Back to listings'),
      h('h1',null,'Edit Listing'),h('p',null,'Update your listing details'),
      h('form',{className:'card bb-form',onSubmit:save,style:{padding:24}},
        h('fieldset',{disabled:busy,style:{border:0,padding:0,margin:0}},
          h('legend',null,'Cover image'),h('img',{src:preview,alt:'Listing cover preview',style:{width:160,height:180,objectFit:'contain',display:'block',margin:'12px 0'}}),
          h('label',null,'Replace cover image (optional)',h('input',{type:'file',accept:'image/jpeg,image/png,image/webp',onChange:event=>setFile(event.target.files?.[0]||null)})),
          h('p',null,'JPG, PNG or WebP, up to 2 MB. Leave empty to keep the current cover.')),
        field('title','Title *',{required:true,maxLength:200}),field('author','Author',{maxLength:150}),field('edition','Edition',{maxLength:50}),
        select('department','Department *',[...new Set([form.department,...(taxonomy?.departments||[]).map(item=>item.name)])].filter(Boolean)),
        field('course_code','Course Code *',{required:true}),field('subject','Subject *',{required:true,maxLength:100}),
        select('item_type','Type',['Textbook','Notes','Lab Manual']),select('condition_type','Condition',['New','Like New','Good','Fair','Poor']),
        field('price','Price (৳) *',{type:'number',required:true,min:'0.01',max:'999999.99',step:'0.01'}),
        h('label',null,'Description *',h('textarea',{className:'input-field',required:true,minLength:5,rows:4,value:form.description,disabled:busy,onChange:event=>change('description',event.target.value)})),
        h('div',{className:'bb-actions'},h('button',{type:'button',className:'btn-secondary',disabled:busy,onClick:()=>navigate('manage-listings')},'Cancel'),
          h('button',{type:'submit',className:'btn-primary',disabled:busy},busy?'Saving…':'Save Changes'))));
  }
  window.BookBridgeUI = {listing, sales, perform, useData, upload, notify, date, imageUrl, usePreview, Requests, Profile, EditListing, useChat, useMarketplace, useReport, extraLinks,
    render(React, C) {
      const h = React.createElement;
      const [user, setUser] = React.useState(null);
      const [loginRole, setLoginRole] = React.useState('buyer');
      const [ready, setReady] = React.useState(false);
      const [view, setView] = React.useState('welcome');
      const [selected, setSelected] = React.useState(null);
      const [publicRows, setPublicRows] = React.useState([]);
      const [publicLoaded,setPublicLoaded] = React.useState(false);
      const [publicFailed,setPublicFailed] = React.useState(false);
      const [taxonomy,setTaxonomy] = React.useState(null);
      const initialized=React.useRef(false);
      const detailRequest=React.useRef(0);
      const [ownRows, setOwnRows] = React.useState([]);
      const [pendingRows, setPendingRows] = React.useState([]);
      const [wishlistRows, setWishlistRows] = React.useState([]);
      const [salesRows, setSalesRows] = React.useState([]);
      const [categories, setCategories] = React.useState([]);
      const [menu, setMenu] = React.useState(false);
      const [notice, setNotice] = React.useState(null);
      const [loading, setLoading] = React.useState(false);
      const [revision, setRevision] = React.useState(0);
      const refresh = () => setRevision(value => value+1);
      const role = user?.role || null;
      const dashboard = role ? `${role}-dashboard` : 'marketplace';
      function navigate(destination) {
        detailRequest.current++;
        if (destination === 'rating' && !selected?.requestId) destination = 'purchase-requests';
        setView(destination); setMenu(false);
        history.replaceState(null, '', `#${destination}${['listing-detail','review-listing','edit-listing','purchase-form','payment'].includes(destination)&&selected ? `/${selected.id}` : destination==='rating'&&selected?.requestId?`/${selected.requestId}`:''}`);
        window.scrollTo({top:0, behavior:'smooth'});
      }
      React.useEffect(() => {
        const session = event => {const value = event.detail.user || null; if(initialized.current){saveError(null);setNotice(null);}setUser(value); setSelected(null);
          setOwnRows([]); setPendingRows([]); setWishlistRows([]); setSalesRows([]); setCategories([]);
          const next=value ? `${value.role}-dashboard` : 'welcome';setView(next);if(initialized.current)history.replaceState(null,'','#'+next);detailRequest.current++;refresh();};
        const message = event => setNotice(event.detail);
        const identity = event => setUser(event.detail.user);
        document.addEventListener('bookbridge:session', session);
        document.addEventListener('bookbridge:notice', message);
        document.addEventListener('bookbridge:identity', identity);
        window.BookBridgeAuth.ready.then(() => {setUser(window.BookBridgeAuth.currentUser);setNotice(restoredError()); setReady(true);
          initialized.current=true;
          const requested = location.hash.slice(1).split('/')[0];
          setView(requested || (window.BookBridgeAuth.currentUser ? `${window.BookBridgeAuth.currentUser.role}-dashboard` : 'welcome'));
        });
        return () => {document.removeEventListener('bookbridge:session',session); document.removeEventListener('bookbridge:notice',message);document.removeEventListener('bookbridge:identity',identity);};
      }, []);
      React.useEffect(() => {if(!ready) return; let active = true; setLoading(true);
        setPublicLoaded(false);setPublicFailed(false);
        const tasks = [API().allListings().then(rows => {if(active){setPublicRows(rows.map(row=>listing(row)));setPublicLoaded(true);}}).catch(error=>{if(active){setPublicRows([]);setPublicFailed(true);}throw error;}),API().categories().then(result=>{if(active)setTaxonomy(result.data);})];
        if(role === 'buyer') tasks.push(API().wishlist().then(result=>{if(active)setWishlistRows(result.data.items.map(row=>listing(row)));}));
        if(role === 'seller') {
          tasks.push(API().sellerListings().then(rows=>{if(active)setOwnRows(rows.map(row=>listing(row)));}));
          tasks.push(API().sellerSales().then(rows=>{if(active)setSalesRows(sales(rows));}));
        }
        if(role === 'admin') {
          tasks.push(API().adminPendingListings().then(rows=>{if(active)setPendingRows(rows.map(row=>listing(row,'pending_approval')));}));
          tasks.push(API().adminCategories().then(result=>{if(active)setCategories(result.data.categories.map(row=>({...row,id:String(row.id),count:'Unavailable'})));}));
        }
        Promise.allSettled(tasks).then(results=>{if(!active)return; const errors=results.filter(result=>result.status==='rejected');
          if(errors.length)notify(errors.map(result=>result.reason.message).join(' '),true); setLoading(false);});
        return()=>{active=false;};
      },[ready, user?.id, role, revision]);
      const publicViews = ['welcome','login','marketplace','browse','listing-detail'];
      const required = view.startsWith('admin-') || ['pending-listings','review-listing','listing-approved','categories','add-category','sales-report'].includes(view) ? 'admin' :
        ['seller-dashboard','add-listing','listing-submitted','manage-listings','sales-history','edit-listing'].includes(view) ? 'seller' :
        ['buyer-dashboard','wishlist','purchase-form','payment','purchase-success','rating'].includes(view) ? 'buyer' : null;
      const blocked = ready && ((!publicViews.includes(view)&&!user) || (required&&role!==required));
      React.useEffect(()=>{if(blocked)navigate(user?dashboard:'login');},[blocked,role]);
      async function select(row, target = 'listing-detail') {
        const sequence=++detailRequest.current;
        setSelected(row);
        if(target === 'listing-detail') {
          const result = await perform(`detail-${row.id}`,()=>API().listingDetail(row.id));
          if(!result||sequence!==detailRequest.current)return; setSelected(listing(result.data.listing));
        }
        setView(target); setMenu(false);
        history.replaceState(null,'',`#${target}/${row.id}`);
      }
      React.useEffect(()=>{if(!ready)return;let active=true;
        async function restore(){const sequence=++detailRequest.current;const [target,id]=location.hash.slice(1).split('/');if(['purchase-success','listing-submitted','listing-approved'].includes(target)){setView(target==='purchase-success'?'purchase-requests':target==='listing-submitted'?'manage-listings':'pending-listings');return;}if(target)setView(target);if(!id)return;
          if(['listing-detail','purchase-form','payment'].includes(target)){
            setSelected(null);const result=await perform('restore-listing-'+sequence,()=>API().listingDetail(id));if(!active||sequence!==detailRequest.current)return;
            if(result)setSelected(listing(result.data.listing));else setView('marketplace');
          }
          if(target==='rating'&&role==='buyer'){
            setSelected(null);const result=await perform('restore-review-'+sequence,()=>API().requestDetail(id));if(!active||sequence!==detailRequest.current)return;
            if(result?.data.can_review)setSelected({...listing({...result.data.listing,seller_id:result.data.seller.id,seller_name:result.data.seller.name}),requestId:result.data.request.id});else {setView('purchase-requests');notify('Choose an eligible completed request to review.',true);}
          }
        }restore();window.addEventListener('hashchange',restore);return()=>{active=false;window.removeEventListener('hashchange',restore);};
      },[ready,role]);
      React.useEffect(()=>{const [target,id]=location.hash.slice(1).split('/');if(!id)return;
        if(target==='review-listing'&&role==='admin')setSelected(pendingRows.find(row=>row.id===id)||null);
        if(target==='edit-listing'&&role==='seller')setSelected(ownRows.find(row=>row.id===id)||null);
      },[role,pendingRows,ownRows,view]);
      const wishlist = wishlistRows.map(row=>row.id);
      const toggle = id => perform(`wishlist-${id}`,async()=>{await API().wishlistToggle(id); const result=await API().wishlist();setWishlistRows(result.data.items.map(row=>listing(row)));refresh();});
      async function moderate(id, action, feedback='') {const result=await perform(`moderate-${id}`,()=>API().adminReviewListing(id,action,feedback)); if(result)refresh(); return Boolean(result);}
      const mark = id => perform(`listing-${id}`,async()=>{const row=ownRows.find(item=>item.id===id); if(!row)throw new Error('Reload your listings first.');
        await (row.status==='sold'?API().markUnsold(id):API().markSold(id)); refresh();});
      const remove = id => perform(`listing-${id}`,async()=>{await API().deleteListing(id);refresh();});
      const categoryAction = async(method,value) => {const result=await perform('category',()=>API()[method](value)); if(result)refresh();return Boolean(result);};
      const props = {navigate, revision, taxonomy};
      let page;
      if(!ready || blocked)page=h('p',{className:'bb-loading'},'Checking your session…');
      else switch(view) {
        case 'welcome':page=h(C.Welcome,{navigate,setRole:setLoginRole,activeListings:publicLoaded?publicRows.length:publicFailed?'Temporarily unavailable':'Loading…'});break;
        case 'login':page=h(C.Login,{role:loginRole,navigate,setRole:setLoginRole});break;
        case 'marketplace':case 'browse':page=h(C.Marketplace,{...props,role:role||'guest',onSelectListing:row=>select(row),wishlist,onWishlistToggle:toggle,onLoginPrompt:()=>navigate('login'),allListings:publicRows});break;
        case 'listing-detail':page=selected?h(C.Detail,{...props,listing:selected,role:role||'guest',isWishlisted:wishlist.includes(selected.id),onWishlistToggle:()=>role==='buyer'?toggle(selected.id):navigate('login'),onLoginPrompt:()=>navigate('login'),onStartChat:()=>{}}):h('p',{className:'bb-loading'},'Loading listing…');break;
        case 'buyer-dashboard':page=h(C.Buyer,{...props,onSelectListing:row=>select(row),wishlist,onWishlistToggle:toggle,allListings:publicRows});break;
        case 'wishlist':page=h(C.Wishlist,{...props,wishlist,onWishlistToggle:toggle,onSelectListing:row=>select(row),allListings:wishlistRows});break;
        case 'purchase-form':case 'payment':case 'purchase-success':case 'rating':page=selected?h(C.Purchase,{...props,listing:selected,currentView:view}):h('p',{className:'bb-loading'},'Select a listing or purchase request first.');break;
        case 'chat':page=h(C.Chat,{...props,role,chatPartner:selected?.seller||'',partnerId:selected?.sellerId,listingId:selected?.id,listingTitle:selected?.title});break;
        case 'seller-dashboard':page=h(C.Seller,{...props,sellerListings:ownRows,sellerSales:salesRows});break;
        case 'add-listing':page=h(C.AddListing,{...props,onAddListing:()=>refresh()});break;
        case 'listing-submitted':page=h(C.Submitted,props);break;
        case 'manage-listings':page=h(C.Manage,{...props,sellerListings:ownRows,onMarkSold:mark,onEditListing:row=>select(row,'edit-listing'),onDeleteListing:remove});break;
        case 'sales-history':page=h(C.Sales,{...props,sellerSales:salesRows});break;
        case 'edit-listing':page=selected?h(EditListing,{React,...props,key:selected.id,listing:selected,onSaveListing:()=>refresh()}):h('p',{className:'bb-loading'},'Select one of your listings first.');break;
        case 'admin-dashboard':page=h(C.Admin,{...props,allListings:pendingRows});break;
        case 'pending-listings':page=h(C.Pending,{...props,allListings:pendingRows,onSelectListing:row=>select(row,'review-listing')});break;
        case 'review-listing':page=h(C.Review,{...props,selectedListing:selected,onApprove:id=>moderate(id,'approve'),onRejectListing:(id,feedback)=>moderate(id,'reject',feedback),onRequestChanges:(id,feedback)=>moderate(id,'changes_requested',feedback)});break;
        case 'listing-approved':page=h(C.Approved,props);break;
        case 'categories':page=h(C.Categories,{...props,categories,onUpdateCategory:value=>categoryAction('adminUpdateCategory',value),onRemoveCategory:id=>categoryAction('adminRemoveCategory',id)});break;
        case 'add-category':page=h(C.AddCategory,{...props,categories,onAddCategory:value=>categoryAction('adminAddCategory',value)});break;
        case 'sales-report':page=h(C.Report,props);break;
        case 'purchase-requests':page=h(window.BookBridgeUI.Requests,{React,...props,role,onRefresh:refresh,onReview:row=>{setSelected({...listing({...row,id:row.listing_id}),requestId:row.id});setView('rating');history.replaceState(null,'','#rating/'+row.id);},onChat:row=>{setSelected({...listing({...row,id:row.listing_id}),sellerId:role==='seller'?row.buyer_id:row.seller_id,seller:role==='seller'?row.buyer_name:row.seller_name});navigate('chat');}});break;
        case 'profile':page=['buyer','seller'].includes(role)?h(window.BookBridgeUI.Profile,{React,...props,role,onSaved:async()=>{const result=await API().me();window.BookBridgeAuth.setUser(result.data.user);}}):h('p',{className:'bb-notice'},'Profile editing is available for buyers and sellers.');break;
        default:page=h(C.Welcome,{navigate,setRole:setLoginRole,activeListings:publicLoaded?publicRows.length:publicFailed?'Temporarily unavailable':'Loading…'});
      }
      return h('div',{style:{minHeight:'100vh',background:'#F6FAFD'}},
        !['welcome','login'].includes(view)&&h(C.Nav,{role,navigate,onLogout:()=>window.BookBridgeAuth.logout(),showProfileMenu:menu,setShowProfileMenu:setMenu}),
        notice&&h('div',{className:`bb-notice ${notice.error?'bb-error':''}`,role:notice.error?'alert':'status'},notice.message,h('button',{type:'button',onClick:()=>{saveError(null);setNotice(null);},'aria-label':'Dismiss'},'×')),
        loading&&h('p',{className:'bb-loading',role:'status'},'Loading server data…'),
        h('main',null,page));
    }
  };
})();
