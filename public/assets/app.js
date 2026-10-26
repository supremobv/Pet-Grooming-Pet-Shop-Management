'use strict';
const sidebar=document.querySelector('#sidebar');
const menuButton=document.querySelector('.mobile-menu');
const mobile=window.matchMedia('(max-width: 780px)');
const setMenu=open=>{if(sidebar){sidebar.classList.toggle('open',open);sidebar.inert=mobile.matches&&!open;}menuButton?.setAttribute('aria-expanded',String(open));};
setMenu(false);
mobile.addEventListener('change',()=>setMenu(false));
menuButton?.addEventListener('click',()=>setMenu(!sidebar.classList.contains('open')));
document.addEventListener('click',event=>{if(sidebar?.classList.contains('open')&&!sidebar.contains(event.target)&&!event.target.closest('.mobile-menu'))setMenu(false);});
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&sidebar?.classList.contains('open')){setMenu(false);menuButton?.focus();}});
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm))event.preventDefault();}));
document.querySelector('#print-receipt')?.addEventListener('click',()=>window.print());
const checkout=document.querySelector('#checkout-form');
if(checkout){
 const money=value=>checkout.dataset.currency+' '+(value/100).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
 const cents=value=>Math.round((Number(value)||0)*100);
 const update=()=>{
  let subtotal=0;const lines=document.querySelector('#cart-lines');lines.replaceChildren();
  const addLine=(name,qty,price)=>{const row=document.createElement('div');row.className='cart-line';const label=document.createElement('span');label.textContent=qty+' × '+name;const total=document.createElement('strong');total.textContent=money(price*qty);row.append(label,total);lines.append(row);subtotal+=price*qty;};
  document.querySelectorAll('.pos-qty').forEach(input=>{const quantity=Math.max(0,Math.trunc(Number(input.value)||0));if(quantity)addLine(input.dataset.name,quantity,Number(input.dataset.price));});
  const appointment=document.querySelector('#appointment_id').selectedOptions[0];if(appointment?.value)addLine(appointment.dataset.name,1,Number(appointment.dataset.price));
  if(!lines.children.length){const text=document.createElement('p');text.className='muted';text.textContent='Your selected items will appear here.';lines.append(text);}
  const discount=cents(document.querySelector('#discount').value);const tax=Math.round(Math.max(0,subtotal-discount)*Number(checkout.dataset.tax)/100);const total=Math.max(0,subtotal-discount)+tax;
  document.querySelector('#subtotal').textContent=money(subtotal);document.querySelector('#discount-total').textContent=money(discount);document.querySelector('#tax-total').textContent=money(tax);document.querySelector('#grand-total').textContent=money(total);document.querySelector('#change-total').textContent=money(Math.max(0,cents(document.querySelector('#tendered').value)-total));
  document.querySelector('#payment_reference').required=document.querySelector('#payment_method').value!=='cash';
 };
 checkout.addEventListener('input',update);checkout.addEventListener('change',update);update();
 document.querySelector('#product-search').addEventListener('input',event=>{const q=event.target.value.toLowerCase().trim();document.querySelectorAll('.product-card').forEach(card=>{card.hidden=!card.dataset.search.includes(q);});});
 checkout.addEventListener('submit',()=>{document.querySelectorAll('.pos-qty').forEach(input=>{if(Number(input.value)===0){input.disabled=true;input.closest('.product-card').querySelector('input[type=hidden]').disabled=true;}});const button=checkout.querySelector('button[type=submit]');setTimeout(()=>{button.disabled=true;button.textContent='Recording payment…';},0);});
}
