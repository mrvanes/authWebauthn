<?php
/**
 * Hook to inject extra html content
 *
 * @param array &$hookingo The hookinfo
 */

function authWebauthn_hook_htmlinject(&$hookinfo) {
    \SimpleSAML\Logger::info("WebAuthn _hook_htmlinject");
    $jsfile = \SimpleSAML\Module::getModuleURL('authWebauthn/resources/js/webauthn.js');
    $hookinfo['head'] = ["<script src='$jsfile'></script>\n", "<script src='https://code.jquery.com/jquery-3.3.1.min.js'></script>\n"];
//     $hookinfo['head'] = ["<script src='$jsfile'></script>\n"];
//     $hookinfo['jquery']['core'] = True;
//     $hookinfo['jquery']['ui'] = True;
}
