import assert from 'node:assert/strict';
import { SECTIONS, readSection, sectionUrl, createWorkspaceRouter } from '../assets/js/workspace/router.js';
import { defaultDataState, readDataState, dataStateUrl, apiParameters } from '../assets/js/workspace/state.js';

const root = 'https://site.test/wp-admin/admin.php?page=core-blueprint-work-workspace';
assert.deepEqual(SECTIONS, ['work-items','projects','services','recurring']);
assert.equal(readSection(root+'&section=recurring'), 'recurring');
assert.equal(sectionUrl(root,'recurring'), '/wp-admin/admin.php?page=core-blueprint-work-workspace&section=recurring');
const current = readDataState(root+'&section=recurring&status=active&sort=status&uwe_page=3', 'recurring');
assert.deepEqual(current,{search:'',sort:'status',status:'active',page:3,per_page:25});
const url = dataStateUrl(root+'&section=recurring&search=old&sort=invalid', 'recurring', current);
assert.equal(new URL(url,root).searchParams.get('page'), 'core-blueprint-work-workspace');
assert.equal(new URL(url,root).searchParams.get('status'),'active');
assert.equal(new URL(url,root).searchParams.get('sort'),'status');
assert.equal(apiParameters(current).get('page'),'3');
assert.equal(readDataState(root+'&section=services&status=active','services').status,'');
assert.equal(defaultDataState('work-items').sort,'workload');
const listeners = new Map();
const browser = {location:{href:root},scrollY:0,history:{
 pushState(_s,_t,url){ browser.location.href = new URL(url,browser.location.href).href; }
},addEventListener(name,fn){listeners.set(name,fn)},removeEventListener(name){listeners.delete(name)}};
const changes=[];
const router=createWorkspaceRouter(browser,(section,detail)=>changes.push({section,source:detail.source}));
assert.equal(router.navigate('recurring'),true);
assert.equal(browser.location.href.includes('section=recurring'),true);
browser.location.href=root+'&section=projects';
listeners.get('popstate')();
assert.deepEqual(changes, [{section:'recurring',source:'push'},{section:'projects',source:'popstate'}]);
router.dispose();
console.log('Work M3-B4-A router/state: PASS (four tabs, URL, search/status, back/forward, native fallback).');
