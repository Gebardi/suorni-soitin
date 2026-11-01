<?php
/* Yhtenäinen viivakuvakesarja (SVG) käyttöliittymän emojien tilalle (9.67). Väri seuraa tekstin väriä (currentColor). */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suorni_icon_set() {
	return array(
		'radio'     => '<rect x="3" y="8" width="18" height="12" rx="2.5"/><path d="M7 8l9-4.5"/><circle cx="9" cy="14" r="2.6"/><path d="M15 12.5h3M15 16h3"/>',
		'trophy'    => '<path d="M8 4h8v5a4 4 0 0 1-8 0V4z"/><path d="M8 6H5.5a2.5 2.5 0 0 0 2.7 3.8M16 6h2.5a2.5 2.5 0 0 1-2.7 3.8"/><path d="M12 13v4M8.5 20h7M9.5 17h5"/>',
		'calendar'  => '<rect x="4" y="5" width="16" height="15" rx="2.5"/><path d="M4 10h16M9 3v4M15 3v4"/>',
		'mic'       => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21M9 21h6"/>',
		'chat'      => '<path d="M6 5h12a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3h-6.5L7 20.5V17H6a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3z"/>',
		'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.8 7.5l8.2 6 8.2-6"/>',
		'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.2 2"/>',
		'gift'      => '<path d="M4.5 11.5h15V20h-15zM3.5 7.5h17v4h-17zM12 7.5V20"/><path d="M12 7.5C10.5 3.5 7 4.5 8.2 6.7c.5.8 2.3.8 3.8.8zM12 7.5c1.5-4 5-3 3.8-.8-.5.8-2.3.8-3.8.8z"/>',
		'thumbup'   => '<path d="M7 11v9H4v-9zM7 11l3.6-6.8a1.8 1.8 0 0 1 2.4-.7 2 2 0 0 1 1 1.8V9h5a2 2 0 0 1 2 2.3l-1.1 6.2a2 2 0 0 1-2 1.7H7"/>',
		'thumbdown' => '<g transform="rotate(180 12 12)"><path d="M7 11v9H4v-9zM7 11l3.6-6.8a1.8 1.8 0 0 1 2.4-.7 2 2 0 0 1 1 1.8V9h5a2 2 0 0 1 2 2.3l-1.1 6.2a2 2 0 0 1-2 1.7H7"/></g>',
		'link'      => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.2 1.2M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.2-1.2"/>',
		'share'     => '<path d="M12 15V4M8 8l4-4 4 4M5 13v5.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V13"/>',
		'note'      => '<path d="M9 18V6.5l10-2V16"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/>',
		'instagram' => '<rect x="4" y="4" width="16" height="16" rx="5"/><circle cx="12" cy="12" r="3.8"/><circle cx="17" cy="7" r=".7" fill="currentColor"/>',
		'tiktok'    => '<path d="M14 4v10.3a3.6 3.6 0 1 1-3.6-3.6M14 4c.4 2.7 2.1 4.4 5 4.6"/>',
		'megaphone' => '<path d="M4 10v4h3l7 4V6L7 10zM17 9.5a4 4 0 0 1 0 5M19.5 7a7.5 7.5 0 0 1 0 10"/>',
		'robot'     => '<rect x="5" y="8" width="14" height="11" rx="3.5"/><path d="M12 8V5"/><circle cx="12" cy="4" r="1"/><path d="M9.2 13h.01M14.8 13h.01M9.5 16.2h5"/>',
		'phone'     => '<rect x="7" y="3" width="10" height="18" rx="2.8"/><path d="M10.5 18h3"/>',
		'users'     => '<circle cx="9" cy="9" r="3.2"/><path d="M3.5 19c.5-3.2 2.7-5 5.5-5s5 1.8 5.5 5M16 6.2a3 3 0 0 1 0 5.6M17.5 14.3c1.8.6 3 2.2 3.2 4.7"/>',
		'headphones' => '<path d="M4 15v-3a8 8 0 0 1 16 0v3"/><rect x="3" y="14" width="4" height="6" rx="1.6"/><rect x="17" y="14" width="4" height="6" rx="1.6"/>',
		'wind'      => '<path d="M3 9h10a2.5 2.5 0 1 0-2.5-2.5M3 14h14a2.5 2.5 0 1 1-2.5 2.5M3 18.5h7"/>',
		'drop'      => '<path d="M12 3.5s6 6.2 6 10.5a6 6 0 0 1-12 0C6 9.7 12 3.5 12 3.5z"/>',
		'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6L7 7M17 17l1.4 1.4M5.6 18.4L7 17M17 7l1.4-1.4"/>',
		'cloud'     => '<path d="M7 18a4.5 4.5 0 0 1 .5-9 5.5 5.5 0 0 1 10.6 1.7A3.7 3.7 0 0 1 17.5 18z"/>',
		'partly'    => '<circle cx="8.5" cy="8" r="2.8"/><path d="M8.5 2.5v1M2.8 8h1M4.5 4l.7.7M12.5 4l-.7.7"/><path d="M8.5 20a4 4 0 0 1 .4-8 5 5 0 0 1 9.6 1.6A3.2 3.2 0 0 1 18 20z"/>',
		'rain'      => '<path d="M7 15a4.5 4.5 0 0 1 .5-9 5.5 5.5 0 0 1 10.6 1.7A3.7 3.7 0 0 1 17.5 15z"/><path d="M8 18l-1 2.2M12.5 18l-1 2.2M17 18l-1 2.2"/>',
		'snow'      => '<path d="M12 3v18M4.5 7.5l15 9M19.5 7.5l-15 9"/>',
		'storm'     => '<path d="M7 14a4.5 4.5 0 0 1 .5-9 5.5 5.5 0 0 1 10.6 1.7A3.7 3.7 0 0 1 17.5 14z"/><path d="M12.8 11l-2.6 4.2h3.2L11 19.5"/>',
		'fog'       => '<path d="M4 8h16M6 12h12M4 16h16M8 20h8"/>',
		'pencil'    => '<path d="M4 20l1-4L16.5 4.5a2.1 2.1 0 0 1 3 3L8 19z"/><path d="M14.5 6.5l3 3"/>',
		'play'      => '<path d="M8 5.5v13l11-6.5z" fill="currentColor"/>',
		'pause'     => '<rect x="6.5" y="5" width="4" height="14" rx="1.2" fill="currentColor"/><rect x="13.5" y="5" width="4" height="14" rx="1.2" fill="currentColor"/>',
		'eject'     => '<path d="M12 5.5l8 9H4z" fill="currentColor"/><path d="M5 19h14"/>',
		'check'     => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		'lock'      => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
		'sparkle'   => '<path d="M12 3l2 5.5 5.5 2-5.5 2-2 5.5-2-5.5-5.5-2 5.5-2z"/>',
		'arrow'     => '<path d="M7 17L17 7M9 7h8v8"/>',
	);
}
/* <svg>-merkkaus yhdelle kuvakkeelle. Sprite (suorni_icon_sprite) tulee olla sivulla. */
function suorni_ic( $name, $cls = '' ) {
	return '<svg class="ic' . ( '' !== $cls ? ' ' . esc_attr( $cls ) : '' ) . '" aria-hidden="true" focusable="false"><use href="#si-' . esc_attr( $name ) . '"/></svg>';
}
function suorni_icon_sprite() {
	$h = '<svg width="0" height="0" style="position:absolute;overflow:hidden" aria-hidden="true" focusable="false"><defs>';
	foreach ( suorni_icon_set() as $k => $v ) {
		$h .= '<symbol id="si-' . esc_attr( $k ) . '" viewBox="0 0 24 24">' . $v . '</symbol>';
	}
	return $h . '</defs></svg>';
}
