import assert from 'node:assert/strict';
import { availableBoardBlock, edgeScrollStep, rememberBoardScroll, restoreBoardScroll, mountBoardViewport } from '../assets/js/workspace/board-viewport.js';

assert.equal(availableBoardBlock(250, 900), 630);
assert.equal(availableBoardBlock(800, 900), 260);
assert.equal(availableBoardBlock(NaN, 900), 260);
assert.equal(edgeScrollStep(2, 0, 400), -18);
assert.equal(edgeScrollStep(399, 0, 400), 18);
assert.equal(edgeScrollStep(200, 0, 400), 0);

function lane(status, scrollTop) {
	const list={scrollTop,scrollHeight:1200,clientHeight:300,getBoundingClientRect(){return {top:280,bottom:580}},querySelectorAll:()=>[]};
	return {dataset:{cbWorkStatusLane:status},querySelector:s=>s==='[data-cb-core-reorder-list]'?list:null,
		getBoundingClientRect(){return status==='planned'?{left:0,right:300}:{left:300,right:900};},list};
}
const left=lane('planned',44),right=lane('blocked',99);
const listeners=[],props={},observed=[],removed=[];
const board={
	isConnected:true,clientWidth:900,scrollWidth:1500,scrollLeft:23,
	style:{setProperty(k,v){props[k]=v},removeProperty(k){delete props[k];}},
	dataset:{},classList:{contains:k=>k==='is-reordering'},
	getBoundingClientRect(){return {top:250,left:0,right:900,bottom:900};},
	querySelectorAll(selector) {
		return selector==='[data-cb-work-status-lane]'?[left,right]:[];
	},
	addEventListener(type,handler,opts){listeners.push({type,handler,opts})},
	hasPointerCapture(){return false},
};
const position=rememberBoardScroll(board);
assert.deepEqual({...position.lanes},{planned:44,blocked:99});
board.scrollLeft=0;left.list.scrollTop=0;
restoreBoardScroll(board,position);
assert.equal(board.scrollLeft,23);
assert.equal(left.list.scrollTop,44);

const fakeWindow={
	innerHeight:900,
	addEventListener(type,handler){listeners.push({type,handler})},
	requestAnimationFrame(callback){this.frame=callback;return 4},
	cancelAnimationFrame(id){this.cancelled=id},
	ResizeObserver:class {observe(x){observed.push(x)} disconnect(){removed.push('disconnected')}},
};
const toolbar={},ctx={};
const destroy=mountBoardViewport(board,fakeWindow,[toolbar,ctx]);
assert.equal(board.dataset.cbWorkBoardViewport,'1');
assert.equal(props['--cb-work-board-available-block'],'630px');
assert.equal(observed.length,2);
listeners.find(x=>x.type==='wheel').handler({
	shiftKey:true,cancelable:true,scrollWidth:1500,deltaX:0,deltaY:100,
	preventDefault(){this.prevented=true},
});
assert.equal(board.scrollLeft,123,'Shift-wheel pans real board');
const pointerMove=listeners.find(x=>x.type==='pointermove');
pointerMove.handler({clientX:899,clientY:500});
assert.equal(board.scrollLeft,141,'Base reorder edge move pans horizontal scroll');
pointerMove.handler({clientX:500,clientY:579});
assert.equal(right.list.scrollTop,117,'long lane scrolls toward its bottom during reorder');
assert.equal(left.list.scrollTop,44,'other lane stays at its own position');
destroy();
assert.equal(board.dataset.cbWorkBoardViewport,undefined);
assert.equal(props['--cb-work-board-available-block'],undefined);
assert.deepEqual(removed,['disconnected']);
console.log('Work M3-UX3-F2E viewport runtime: PASS (geometry, positions, wheel, drag edge, teardown).');
