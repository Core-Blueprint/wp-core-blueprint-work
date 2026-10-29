<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$mount = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$mutations = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mutations.js' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$check = (string) file_get_contents( $root . '/tools/check' );
$runtime = (string) file_get_contents( $root . '/tests/work-uwe-03c2-runtime.mjs' );
$mutation_runtime = (string) file_get_contents( $root . '/tests/work-m1-mutations-runtime.mjs' );

$checks = [
	'Protected Golden renderer remains byte-exact' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500'
			=== sha1( 'blob ' . strlen( $operations ) . "\0" . $operations ),
	'Transient same-view errors retain the last canonical view only' =>
		str_contains( $mount, 'export function canonicalReadFailureMode(' )
		&& str_contains( $mount, "renderedView === requestedView ? 'retain' : 'fallback'" )
		&& str_contains( $mount, "mode === 'retain'" )
		&& str_contains( $mount, "destination.hidden = false;" )
		&& str_contains( $mount, "mutationAdapter?.disable();" ),
	'401/403 removes private HTML, suppresses SSR fallback and dispatches unmount' =>
		str_contains( $mount, "status === 401 || status === 403" )
		&& str_contains( $mount, "mode === 'unauthorized'" )
		&& str_contains( $mount, "destination.replaceChildren();" )
		&& str_contains( $mount, "new CustomEvent('cb-work:canonical-view-unmount'" )
		&& str_contains( $mount, "node.hidden = authorizationDenied ? true : originalHidden[index]" ),
	'Successful authorized retry resets denial and restores canonical view' =>
		str_contains( $mount, 'authorizationDenied = false;' )
		&& str_contains( $mount, 'renderedView = view;' )
		&& str_contains( $mount, 'if (error) error.hidden = true;' )
		&& str_contains( $mount, 'if (error) error.hidden = false;' ),
	'Outdated requests remain cancelled, and retry uses existing Work M1 route' =>
		str_contains( $mount, 'ticket !== serial || controller.signal.aborted' )
		&& str_contains( $mount, 'if (current) current.abort();' )
		&& str_contains( $mount, 'current?.abort();' )
		&& ! str_contains( $mount, "method: 'POST'" ),
	'Denied mutations trigger guarded read reauthorization, but 409 stays recoverable' =>
		str_contains( $mutations, 'if (response.status === 401 || response.status === 403)' )
		&& str_contains( $mutations, 'if (!panel.hidden) await refresh();' )
		&& str_contains( $mutations, 'if (!response.ok || !result.success)' )
		&& str_contains( $mutations, 'form.removeAttribute(' )
		&& str_contains( $mutations, "destination.removeAttribute('aria-busy')" ),
	'Runtime cases include same-view transient, permission purge, retry and 403 write' =>
		str_contains( $runtime, 'canonicalReadFailureMode(401' )
		&& str_contains( $runtime, 'A revoked permission is not a transient network error' )
		&& str_contains( $runtime, 'A successful authenticated retry' )
		&& str_contains( $mutation_runtime, '403 write denial rechecks the canonical permission boundary' )
		&& str_contains( $check, 'tests/work-m1-read-recovery-smoke.php' ),
];
foreach ( $checks as $name => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "Work M1 read recovery source FAIL: {$name}\n" );
		exit( 1 );
	}
}
echo "Work M1 read recovery source contract: PASS.\n";
