/* Original frontend API adapter; authentication and CSRF stay in auth.js. */
(() => {
 'use strict';
 async function request(path, options={}) {await window.BookBridgeAuth.ready; return window.BookBridgeAuth.request(path,options);}
 const get=path=>request(path);
 const write=(path,body,method='POST')=>request(path,{method,body});
 async function listingDetail(id) {
   const result=await get('marketplace/listing-details.php?id='+encodeURIComponent(id));
   const row=result.data.listing;
   if(row.seller_id) {
     try {
       const reviews=await get('reviews/seller-reviews.php?seller_id='+encodeURIComponent(row.seller_id));
       row.review_count=reviews.data.seller.total_reviews;
       row.seller_rating=row.review_count>0?reviews.data.seller.average_rating:null;
     } catch(error) {
       row.review_count=null;row.seller_rating=null;
       window.BookBridgeUI?.notify('Seller reviews unavailable. '+error.message,true);
     }
   }
   return result;
 }
 async function pages(path,key) {const rows=[];for(let page=1;;page++){const result=await get(path+(path.includes('?')?'&':'?')+'per_page=100&page='+page);if(!Array.isArray(result.data?.[key]))throw new Error('Invalid list response.');rows.push(...result.data[key]);if(page>=(result.data.pagination?.total_pages||1))return rows;}}
 window.BookBridgeAPI={request,pages,
 me:()=>get('auth/me.php'),logout:()=>window.BookBridgeAuth.logout(),
 login:body=>write('auth/login.php',body),register:body=>write('auth/register.php',body),
 listings:(query='')=>get('marketplace/listings.php'+query),allListings:(query='')=>pages('marketplace/listings.php'+query,'listings'),
 listingDetail,categories:()=>get('marketplace/categories.php'),
 buyerDashboard:()=>get('buyer/dashboard.php'),wishlist:()=>get('buyer/wishlist.php'),wishlistToggle:id=>write('buyer/wishlist.php',{listing_id:Number(id)}),
 purchaseRequests:()=>get('buyer/my-requests.php'),requestDetail:id=>get('buyer/request-detail.php?id='+encodeURIComponent(id)),
 sendPurchaseRequest:body=>write('buyer/purchase-request.php',{...body,payment_method:'cash_on_meet'}),cancelPurchaseRequest:id=>write('buyer/cancel-request.php',{request_id:Number(id)}),
 sellerDashboard:()=>get('seller/dashboard.php'),sellerListings:()=>pages('seller/listings.php','listings'),sellerSales:()=>pages('seller/sales-history.php','sales'),
 addListing:body=>write('seller/add-listing.php',body),editListing:body=>write('seller/edit-listing.php',body,'PUT'),
 markSold:id=>write('seller/mark-sold.php',{listing_id:Number(id)}),markUnsold:id=>write('seller/mark-unsold.php',{listing_id:Number(id)}),deleteListing:id=>write('seller/delete-listing.php',{listing_id:Number(id)}),
 uploadImage:file=>{const body=new FormData();body.append('image',file);return write('seller/upload-image.php',body);},
 sellerRequests:()=>get('buyer/seller-requests.php'),sellerRequestAction:(id,action)=>write('buyer/seller-request-action.php',{request_id:Number(id),action}),
 profile:role=>get(role+'/profile.php'),updateProfile:(role,body)=>write(role+'/profile.php',body,'PUT'),
 conversations:()=>get('messages/conversations.php'),thread:id=>get('messages/thread.php?with_user_id='+encodeURIComponent(id)),sendMessage:body=>write('messages/send.php',body),
 sellerReviews:id=>get('reviews/seller-reviews.php?seller_id='+encodeURIComponent(id)),submitReview:body=>write('reviews/create.php',body),
 adminDashboard:()=>get('admin/dashboard.php'),adminPendingListings:()=>pages('admin/pending-listings.php','listings'),adminCategories:()=>get('admin/categories.php'),
 adminReviewListing:(id,action,feedback='')=>write('admin/review-listing.php',{listing_id:Number(id),action,admin_feedback:feedback}),
 adminAddCategory:body=>write('admin/categories.php',body),adminUpdateCategory:body=>write('admin/categories.php',{id:Number(body.id),name:body.name},'PUT'),adminRemoveCategory:id=>write('admin/categories.php',{id:Number(id)},'DELETE'),
 adminSalesReport:query=>get('admin/sales-report.php'+(query||''))};
})();
