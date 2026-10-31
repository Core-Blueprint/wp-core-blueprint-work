/**
 * M3-UX3-F2E: viewport-aware scrolling exclusively for the SPA Tasks Board.
 *
 * The canonical Board/drag DOM, WordPress fallback, and Base reorder controller
 * remain authoritative. One native horizontal scroll container owns all lanes;
 * each lane list scrolls vertically when long. No fake synchronized scrollbar.
 */
const VIEWPORT_MIN = 260;
const VIEWPORT_GUTTER = 20;
const EDGE = 48;
const STEP = 18;

export function availableBoardBlock(top, viewportHeight, gutter = VIEWPORT_GUTTER) {
	if (!Number.isFinite(top) || !Number.isFinite(viewportHeight) || viewportHeight <= 0) return VIEWPORT_MIN;
	return Math.max(VIEWPORT_MIN, Math.floor(viewportHeight - Math.max(0, top) - gutter));
}

/** Preserve position across authorized same-view rehydration, never across a view change. */
export function rememberBoardScroll(board) {
	if (!board) return null;
	const lanes = Object.create(null);
	for (const lane of board.querySelectorAll?.('[data-cb-work-status-lane]') || []) {
		const list = lane.querySelector?.('[data-cb-core-reorder-list]');
		if (list) lanes[lane.dataset.cbWorkStatusLane] = list.scrollTop || 0;
	}
	return { inline: board.scrollLeft || 0, lanes };
}

export function restoreBoardScroll(board, position) {
	if (!board || !position) return;
	board.scrollLeft = position.inline || 0;
	for (const lane of board.querySelectorAll?.('[data-cb-work-status-lane]') || []) {
		const list = lane.querySelector?.('[data-cb-core-reorder-list]');
		if (list && Object.hasOwn(position.lanes, lane.dataset.cbWorkStatusLane)) {
			list.scrollTop = position.lanes[lane.dataset.cbWorkStatusLane];
		}
	}
}

/** Pure pointer-edge step: positive goes right/down, negative left/up. */
export function edgeScrollStep(point, start, end) {
	if (![point,start,end].every(Number.isFinite) || end <= start) return 0;
	if (point < start + EDGE) return -Math.ceil(STEP * Math.min(1, (start + EDGE - point) / EDGE));
	if (point > end - EDGE) return Math.ceil(STEP * Math.min(1, (point - (end - EDGE)) / EDGE));
	return 0;
}

/**
 * Short-lived owner for a single mounted board. Returns a destroy callback.
 * Frame work is cancelled on route changes/403/abort to avoid stale DOM writes.
 */
export function mountBoardViewport(board, browser = window, observe = []) {
	if (!board?.querySelectorAll || !board?.getBoundingClientRect) return () => {};
	let closed = false;
	let frame = null;
	let panning = null;
	const abort = new AbortController();
	const signal = abort.signal;
	const raf = typeof browser.requestAnimationFrame === 'function'
		? browser.requestAnimationFrame.bind(browser) : (callback) => browser.setTimeout(callback, 0);
	const cancel = typeof browser.cancelAnimationFrame === 'function'
		? browser.cancelAnimationFrame.bind(browser) : browser.clearTimeout?.bind(browser);

	const measure = () => {
		frame = null;
		if (closed || !board.isConnected) return;
		const top = board.getBoundingClientRect().top - (browser.visualViewport?.offsetTop || 0);
		const height = browser.visualViewport?.height || browser.innerHeight;
		board.style.setProperty('--cb-work-board-available-block', availableBoardBlock(top, height) + 'px');
	};
	const schedule = () => {
		if (closed || frame !== null) return;
		frame = raf(measure);
	};
	const observer = typeof browser.ResizeObserver === 'function' ? new browser.ResizeObserver(schedule) : null;
	for (const element of observe) if (element && observer) observer.observe(element);
	board.dataset.cbWorkBoardViewport = '1';
	measure();
	browser.addEventListener('resize', schedule, { signal });
	browser.addEventListener('scroll', schedule, { passive: true, signal });
	browser.visualViewport?.addEventListener?.('resize', schedule, { signal });
	browser.visualViewport?.addEventListener?.('scroll', schedule, { signal });

	// Platform wheel scrolling is native; Shift+wheel on a non-trackpad mouse
	// is a progressive enhancement and runs only when horizontal overflow exists.
	board.addEventListener('wheel', (event) => {
		if (!event.shiftKey || !event.cancelable || board.scrollWidth <= board.clientWidth
			|| Math.abs(event.deltaX) >= Math.abs(event.deltaY)) return;
		board.scrollLeft += event.deltaY;
		event.preventDefault();
	}, { passive: false, signal });

	// Dragging blank *gaps* pans horizontally; no pointer interception inside
	// cards, links, lane headers, lists or Base's reorder handles.
	board.addEventListener('pointerdown', (event) => {
		if (event.target !== board || event.button !== 0 || event.pointerType !== 'mouse') return;
		panning = { id: event.pointerId, x: event.clientX, left: board.scrollLeft };
	}, { signal });
	board.addEventListener('pointermove', (event) => {
		if (panning && event.pointerId === panning.id) {
			if (Math.abs(event.clientX - panning.x) > 4) {
				if (!board.hasPointerCapture?.(event.pointerId)) board.setPointerCapture?.(event.pointerId);
				board.scrollLeft = panning.left + panning.x - event.clientX;
				if (event.cancelable) event.preventDefault();
			}
			return;
		}
		// Base Reorder continues to choose the target, enforce transition ACL
		// and persist. Work only scrolls the already-scrollable containers.
		if (!board.classList?.contains('is-reordering')) return;
		const bounds = board.getBoundingClientRect();
		if (event.clientY < bounds.top || event.clientY > bounds.bottom) return;
		const sideways = edgeScrollStep(event.clientX, bounds.left, bounds.right);
		if (sideways) board.scrollLeft += sideways;
		const lane = [...board.querySelectorAll('[data-cb-work-status-lane]')]
			.find((candidate) => {
				const box = candidate.getBoundingClientRect();
				return event.clientX >= box.left && event.clientX <= box.right;
			});
		const list = lane?.querySelector('[data-cb-core-reorder-list]');
		if (list && list.scrollHeight > list.clientHeight) {
			const rect = list.getBoundingClientRect();
			const step = edgeScrollStep(event.clientY, rect.top, rect.bottom);
			if (step) list.scrollTop += step;
		}
	}, { signal });
	const stopPan = (event) => {
		if (!panning || event.pointerId !== panning.id) return;
		if (board.hasPointerCapture?.(event.pointerId)) board.releasePointerCapture?.(event.pointerId);
		panning = null;
	};
	board.addEventListener('pointerup', stopPan, { signal });
	board.addEventListener('pointercancel', stopPan, { signal });

	return () => {
		if (closed) return;
		closed = true;
		abort.abort();
		observer?.disconnect();
		if (frame !== null && cancel) cancel(frame);
		frame = null;
		panning = null;
		board.style.removeProperty('--cb-work-board-available-block');
		delete board.dataset.cbWorkBoardViewport;
	};
}
