<?php
/**
 * Hook to inject extra html content
 *
 * @param array &$hookingo The hookinfo
 */

function authWebauthn_hook_htmlinject(&$hookinfo) {
    $jsfile = \SimpleSAML\Module::getModuleURL('authWebauthn/resources/js/webauthn.js');
    $hookinfo['head'] = ["<script src='$jsfile'></script>\n"];
    $hookinfo['jquery']['core'] = True;
}
