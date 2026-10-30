import assert from 'node:assert/strict';
import { isolateOperations } from '../assets/js/workspace/app.js';

const closed = [];
const views = {
	engagements: { collapse() { closed.push('engagements'); } },
	time: { collapse() { closed.push('time'); } },
};
isolateOperations('engagements', views);
assert.deepEqual(closed, ['time'], 'opening Service Operations collapses only Time');
isolateOperations('time', views);
assert.deepEqual(closed, ['time', 'engagements'], 'opening Time collapses only Service Operations');
isolateOperations('section', views);
assert.deepEqual(closed.slice(-2), ['engagements', 'time'], 'switching section collapses both');
assert.doesNotThrow(() => isolateOperations('time', { engagements: null, time: null }),
	'native progressive fallback has no JS view instance');
console.log('Work M3-UX1 Operations coordinator: PASS (exclusive surfaces, navigation, fallback).');
