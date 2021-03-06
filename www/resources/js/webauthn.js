/*

This is key registration part of the client (browser) side of webauthn
authentication.

This really does little more than fetch the info from the physical key
or fingerprint reader etc, and repackage it in a palatable form for
sending to the server.

When registering a user account, or allowing them to add a key in their profile,
or whatever, request a challenge from $webauthn->challenge() (e.g. using Ajax)
and pass the resulting key string to
  webauthnRegister(key, cb)
where key is the contents of the hidden field (or however else you stored
the challenge string).

The function will ask the browser to identify their key or touch fingerprint
or whatever.

On completion it will call the callback function cb:
  function cb(success, info)
success is a boolean, true for successful acquisition of info from the key,
in which case pass the info back to the server, call $webauth->register to
validate it, and put the resulting string back in the user record for use
in future logins.

If success is false, then either info is the string 'abort', meaning the
user failed to complete the process, or an error message of whatever else
went wrong.

*/

function webauthnRegister(key, cb){
	key = JSON.parse(key);
	key.publicKey.attestation = undefined;
	key.publicKey.challenge = new Uint8Array(key.publicKey.challenge); // convert type for use by key
	key.publicKey.user.id = new Uint8Array(key.publicKey.user.id);
	key.publicKey.excludeCredentials.forEach(function(k, idx){
		key.publicKey.excludeCredentials[idx].id = new Uint8Array(k.id);
	});

	// console.log(key);
	navigator.credentials.create({publicKey: key.publicKey}).
	then(function (aNewCredentialInfo) {
		// console.log("Credentials.Create response: ", aNewCredentialInfo);
		var cd = JSON.parse(String.fromCharCode.apply(null, new Uint8Array(aNewCredentialInfo.response.clientDataJSON)));
		if (key.b64challenge != cd.challenge) { cb(false, 'key returned something unexpected (1)'); }
		if ('https://'+key.publicKey.rp.name != cd.origin) { return cb(false, 'key returned something unexpected (2)'); }
		if (! ('type' in cd)) {  return cb(false, 'key returned something unexpected (3)'); }
		if (cd.type != 'webauthn.create') { return cb(false, 'key returned something unexpected (4)'); }

		var ao = [];
		(new Uint8Array(aNewCredentialInfo.response.attestationObject)).forEach(function(v){ ao.push(v); });
		var rawId = [];
		(new Uint8Array(aNewCredentialInfo.rawId)).forEach(function(v){ rawId.push(v); });
		var info = {
			rawId: rawId,
			id: aNewCredentialInfo.id,
			type: aNewCredentialInfo.type,
			response: {
				attestationObject: ao,
				clientDataJSON:
				  JSON.parse(String.fromCharCode.apply(null, new Uint8Array(aNewCredentialInfo.response.clientDataJSON)))
			}
		};
		cb(true, JSON.stringify(info));
	}).
	catch(function(aErr) {
		if (("name" in aErr) && (aErr.name == "AbortError" || aErr.name == "NS_ERROR_ABORT") ||
			aErr.name == 'NotAllowedError')
		{
			cb(false, 'abort');
		} else {
			cb(false, aErr.toString());
		}
	});
}

/*

This is login part of the client (browser) side of webauthn authentication.

This really does little more than fetch the info from the physical key
or fingerprint reader etc, and repackage it in a palatable form for
sending to the server.

When generating the login page on the server, request a challenge from
webauthn->challenge(), and put the result into a hidden field on the
login form (which will also need your means to identify the user,
e.g. email address), probably as well as alternative means to log in
(such as a password login), or perhaps you're using the key as a
second factor, so this will be the second page or step in the login
sequence.

When they submit the form, call:
  webauthnAuthenticate(key, cb)
where key is the contents of the hidden field (or however else you stored
the challenge string).

The function will ask the browser to get credentials from the user, prompting
them to plug in the key, or touch finger or whatever.

On completion it will call the callback function cb:
  function cb(success, info)
success is a boolean, true for successful acquisition of info from the key,
in which case put info in the hidden field and continue with the submit
(or do an Ajax POST with the info, or whatever) and when received on the
server side call webauthn->authenticate.

If success is false, then either info is the string 'abort', meaning the
user failed to complete the process, or an error message of whatever else
went wrong.

*/

function webauthnValidate(key, cb){
	var pk = JSON.parse(key);
	var originalChallenge = pk.challenge;
	pk.challenge = new Uint8Array(pk.challenge);
	pk.allowCredentials.forEach(function(k, idx){
		pk.allowCredentials[idx].id = new Uint8Array(k.id);
	});
	/* ask the browser to prompt the user */
	navigator.credentials.get({publicKey: pk})
		.then(function(aAssertion) {
			// console.log("Credentials.Get response: ", aAssertion);
			var ida = [];
			(new Uint8Array(aAssertion.rawId)).forEach(function(v){ ida.push(v); });
			var cd = JSON.parse(String.fromCharCode.apply(null,
														  new Uint8Array(aAssertion.response.clientDataJSON)));
			var cda = [];
			(new Uint8Array(aAssertion.response.clientDataJSON)).forEach(function(v){ cda.push(v); });
			var ad = [];
			(new Uint8Array(aAssertion.response.authenticatorData)).forEach(function(v){ ad.push(v); });
			var sig = [];
			(new Uint8Array(aAssertion.response.signature)).forEach(function(v){ sig.push(v); });
			var info = {
				type: aAssertion.type,
				originalChallenge: originalChallenge,
				rawId: ida,
				response: {
					authenticatorData: ad,
					clientData: cd,
					clientDataJSONarray: cda,
					signature: sig
				}
			};
			cb(true, JSON.stringify(info));
		})
		.catch(function (aErr) {
			if (("name" in aErr) && (aErr.name == "AbortError" || aErr.name == "NS_ERROR_ABORT" ||
									 aErr.name == "NotAllowedError")) {
				cb(false, 'abort');
			} else {
				cb(false, aErr.toString());
			}
		});
}

