<?php
/**
 * Hook to inject extra html content
 *
 * @param array &$hookingo The hookinfo
 */

function authwebauthn_hook_htmlinject(&$hookinfo) {
    $jsfile = \SimpleSAML\Module::getModuleURL('authwebauthn/resources/js/webauthn.js');
    $hookinfo['head'] = ["<script src='$jsfile'></script>\n"];
    $hookinfo['jquery']['core'] = True;
}
