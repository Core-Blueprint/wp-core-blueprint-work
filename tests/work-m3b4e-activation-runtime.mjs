import assert from 'node:assert/strict';
import { mountRecurringWorkspace } from '../assets/js/workspace/recurring.js';

// Exercise real Work click delegation and public Base Modal, no private hooks.
const requests = [], confirmations = [], refreshed = [];
let nextConfirmation = false;
let click;
const panel = {
  dataset: {
    cbWorkRecurringMounted: '',
    cbWorkUweEndpoint: 'https://example.test/wp-json/cb-work/v1/workspace/recurring',
    cbWorkRecurringTrash:'Trash', cbWorkRecurringDelete:'Delete Permanently',
    cbWorkRecurringActivate:'Activate',cbWorkRecurringPendingSince:'Pending since',
    cbWorkRecurringActivateWarning:'Activating this rule may generate Work Items for past scheduled dates. Continue?',
  },
  querySelector() { return null; },
  contains() { return true; },
};
const root = {
  dataset:{cbWorkUweNonce:'rest-nonce'},
  ownerDocument:{createElement() {return {hidden:false,setAttribute(){},textContent:''};}},
  querySelector(selector) { return selector === '[data-cb-work-uwe-panel="recurring"]' ? panel : null; },
  addEventListener(name,listener) { if(name==='click') click=listener; },
};
const browser = {
  location:{href:'https://example.test/wp-admin/admin.php?page=work&section=recurring'},
  cbCore:{modal:{async show(config) {confirmations.push(config);return nextConfirmation;}}},
  async fetch(path,options) {
    requests.push({path,options});
    return {ok:true,status:200,json:async()=>({id:17,revision:6,is_active:true,is_trashed:false})};
  },
};
mountRecurringWorkspace(root,browser,async section=>{refreshed.push(section);});
assert.equal(typeof click,'function','delegated events bound');
function action(name,pendingDate='') {
  const target = {
    dataset: {cbWorkRecurringAction:name,ruleId:'17',revision:'5',...(pendingDate ? {pendingSince:pendingDate}:{})},
    disabled:false,
    closest() {return this;},
    hasAttribute(attr) {return attr==='data-cb-work-recurring-action';},
  };
  let prevented=false;
  click({target,defaultPrevented:false,button:0,ctrlKey:false,metaKey:false,altKey:false,shiftKey:false,
    preventDefault() {prevented=true;}});
  assert.equal(prevented,true,'SPA action intercepted');
  return target;
}
const tick=()=>new Promise(resolve=>setTimeout(resolve,0));
nextConfirmation=false;
action('activate','October 14, 2026');
await tick();
assert.equal(confirmations.length,1);
assert.equal(confirmations[0].title,'Activate');
assert.match(confirmations[0].body,/past scheduled dates/);
assert.match(confirmations[0].body,/October 14, 2026/);
assert.equal(requests.length,0,'Cancel never reaches REST');
nextConfirmation=true;
action('activate','October 14, 2026');
await tick();
assert.equal(requests.length,1);
assert.equal(requests[0].options.method,'POST');
assert.equal(requests[0].options.headers['X-WP-Nonce'],'rest-nonce');
assert.deepEqual(JSON.parse(requests[0].options.body),{revision:5,action:'activate',confirmed:true},
  'overdue activation is explicitly confirmed');
assert.deepEqual(refreshed,['recurring']);
const modalBefore=confirmations.length;
action('activate'); await tick();
assert.equal(confirmations.length,modalBefore,'future activation needs no extra warning');
assert.deepEqual(JSON.parse(requests[1].options.body),{revision:5,action:'activate'});
console.log('Work M3-B4-B5 activation runtime: PASS (cancel, informed confirm, nonce, normal lifecycle).');
