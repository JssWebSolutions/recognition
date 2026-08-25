/*!
 * Face Recognition Login – Profile Enrollment Controller
 *
 * Handles the two enrollment flows that previously shipped as inline scripts
 * inside recognition.php:
 *
 *   1. Password-reset / "set new password" page (after registration).
 *   2. WordPress user-profile screen.
 *
 * Both flows read configuration from the global `frlProfileConfig` object
 * (created server-side via wp_localize_script). All user-visible strings
 * are routed through wp.i18n so they can be translated.
 *
 * This file replaces ~280 lines of inline JS. Extracted during the
 * pre-release audit (T1-5, T1-11).
 */
( function ( $ ) {
    'use strict';

    var win = window;

    // Lazy i18n: wp.i18n is only available once the wp-i18n script has loaded.
    // We resolve the `__` function at call time so the JS file does not need
    // to be gated behind `wp-i18n` as a hard dependency.
    function t( key, fallback ) {
        if ( win.wp && win.wp.i18n && typeof win.wp.i18n.__ === 'function' ) {
            return win.wp.i18n.__( key );
        }
        return fallback;
    }

    function cfg() {
        return ( win.frlProfileConfig && win.frlProfileConfig.i18n ) || {};
    }

    // ---------------------------------------------------------------------
    // Flow 1 – password-reset / new-user "set password" enrollment modal.
    // Hooks are bound to the .frl-password-page-section container which only
    // exists when the password-page flow has rendered the inline HTML.
    // ---------------------------------------------------------------------
    function initPasswordFlow() {
        var videoEl      = null;
        var videoStream  = null;
        var modelsLoaded = false;
        var samples      = [];
        var processing   = false;

        $( document ).on( 'click', '#frl-enroll-face-btn', async function () {
            $( '#frl-enroll-modal' ).show();
            videoEl = document.getElementById( 'frl-video' );
            await loadModels();
            await startVideo();
            await startEnrollment();
        } );

        $( document ).on( 'click', '.frl-close', function () {
            $( '#frl-enroll-modal' ).hide();
            stopVideo();
            processing = false;
        } );

        async function loadModels() {
            if ( modelsLoaded ) { return; }
            try {
                $( '#frl-status' )
                    .text( cfg().initializing )
                    .addClass( 'frl-status-info' );
                await faceapi.nets.tinyFaceDetector.loadFromUri( win.frlProfileConfig.modelsUrl );
                await faceapi.nets.faceLandmark68Net.loadFromUri( win.frlProfileConfig.modelsUrl );
                await faceapi.nets.faceRecognitionNet.loadFromUri( win.frlProfileConfig.modelsUrl );
                modelsLoaded = true;
                $( '#frl-status' )
                    .text( cfg().cameraReady )
                    .removeClass( 'frl-status-info' )
                    .addClass( 'frl-status-success' );
            } catch ( e ) {
                $( '#frl-status' )
                    .text( cfg().modelsError || cfg().cameraError )
                    .addClass( 'frl-status-error' );
            }
        }

        async function startVideo() {
            try {
                videoStream = await navigator.mediaDevices.getUserMedia( {
                    video: { width: 480, facingMode: 'user' }
                } );
                videoEl.srcObject = videoStream;
                await videoEl.play();
            } catch ( e ) {
                $( '#frl-status' )
                    .text(
                        e.name === 'NotAllowedError'
                            ? cfg().permissionDenied
                            : cfg().cameraError
                    )
                    .addClass( 'frl-status-error' );
            }
        }

        function stopVideo() {
            if ( videoStream ) {
                videoStream.getTracks().forEach( function ( track ) { track.stop(); } );
                videoStream = null;
            }
        }

        async function startEnrollment() {
            processing = true;
            samples = [];
            var required = 15;
            $( '#frl-progress-bar' ).css( 'width', '0%' );
            $( '#frl-progress-text' ).text( '0/' + required );
            $( '#frl-status' )
                .text( cfg().detectingFace )
                .removeClass( 'frl-status-error frl-status-success' )
                .addClass( 'frl-status-info' );

            var options = new faceapi.TinyFaceDetectorOptions( {
                inputSize: 416,
                scoreThreshold: 0.5
            } );

            ( function capture() {
                if ( samples.length >= required ) {
                    completeEnrollment();
                    return;
                }
                if ( ! processing ) { return; }

                faceapi
                    .detectSingleFace( videoEl, options )
                    .withFaceLandmarks()
                    .withFaceDescriptor()
                    .then( function ( result ) {
                        if ( result ) {
                            samples.push( result.descriptor );
                            var pct = ( samples.length / required ) * 100;
                            $( '#frl-progress-bar' ).css( 'width', pct + '%' );
                            $( '#frl-progress-text' ).text( samples.length + '/' + required );
                            $( '#frl-status' )
                                .text( cfg().faceDetected + ' (' + samples.length + ')' )
                                .addClass( 'frl-status-success' );
                        }
                        setTimeout( capture, 200 );
                    } );
            }() );
        }

        async function completeEnrollment() {
            $( '#frl-status' )
                .text( cfg().processing )
                .addClass( 'frl-status-info' );

            var avg = new Array( 128 ).fill( 0 );
            for ( var d of samples ) {
                for ( var i = 0; i < 128; i++ ) { avg[ i ] += d[ i ]; }
            }
            for ( var j = 0; j < 128; j++ ) { avg[ j ] /= samples.length; }

            var formData = new FormData();
            formData.append( 'action', 'frl_enroll_face' );
            formData.append( 'nonce', win.frlProfileConfig.nonce );
            formData.append( 'descriptor', JSON.stringify( Array.from( avg ) ) );
            formData.append( 'device_name', getDeviceName() );

            try {
                var response = await fetch( win.frlProfileConfig.ajaxUrl, {
                    method: 'POST',
                    body: formData
                } );
                var data = await response.json();

                if ( data.success ) {
                    $( '#frl-status' )
                        .text( cfg().enrollmentComplete )
                        .removeClass( 'frl-status-info' )
                        .addClass( 'frl-status-success' );
                    processing = false;
                    stopVideo();
                    document.cookie = 'frl_just_enrolled=1; path=/; max-age=3600';
                    setTimeout( function () {
                        $( '#frl-enroll-modal' ).hide();
                        window.location.reload();
                    }, 1500 );
                } else {
                    $( '#frl-status' )
                        .text( data.data.message || cfg().enrollmentFailed )
                        .addClass( 'frl-status-error' );
                    processing = true;
                }
            } catch ( e ) {
                $( '#frl-status' )
                    .text( cfg().enrollmentFailed )
                    .addClass( 'frl-status-error' );
                processing = true;
            }
        }

        function getDeviceName() {
            var ua = navigator.userAgent;
            if ( /iphone|ipad/i.test( ua ) ) { return 'iPhone/iPad'; }
            if ( /android/i.test( ua ) )    { return 'Android Device'; }
            if ( /windows/i.test( ua ) )    { return 'Windows PC'; }
            if ( /mac/i.test( ua ) )        { return 'Mac'; }
            return 'Unknown Device';
        }
    }

    // ---------------------------------------------------------------------
    // Flow 2 – WordPress user-profile enrollment modal.
    // ---------------------------------------------------------------------
    function initProfileFlow() {
        var frlProfileVideo       = null;
        var frlProfileStream      = null;
        var frlProfileModelsLoaded = false;
        var frlProfileSamples     = [];
        var frlProfileProcessing  = false;
        var frlProfileVideoStream = null;

        $( document ).on( 'click', '#frl-enroll-face-profile-btn', async function () {
            if ( frlProfileProcessing ) { return; }

            $( '#frl-enroll-modal-profile' ).show();
            frlProfileVideo = document.getElementById( 'frl-video-profile' );

            try {
                await loadProfileModels();
                await startProfileVideo();
                await startProfileEnrollment();
            } catch ( e ) {
            }
        } );

        $( document ).on( 'click', '.frl-close-profile', function () {
            $( '#frl-enroll-modal-profile' ).hide();
            stopProfileVideo();
            frlProfileProcessing = false;
        } );

        $( document ).on( 'click', '#frl-enroll-modal-profile', function ( e ) {
            if ( e.target === this ) {
                $( this ).hide();
                stopProfileVideo();
                frlProfileProcessing = false;
            }
        } );

        async function loadProfileModels() {
            if ( frlProfileModelsLoaded ) { return; }
            showProfileStatus( cfg().initializing, 'info' );
            try {
                await faceapi.nets.tinyFaceDetector.loadFromUri( win.frlProfileConfig.modelsUrl );
                await faceapi.nets.faceLandmark68Net.loadFromUri( win.frlProfileConfig.modelsUrl );
                await faceapi.nets.faceRecognitionNet.loadFromUri( win.frlProfileConfig.modelsUrl );
                frlProfileModelsLoaded = true;
                showProfileStatus( cfg().cameraReady, 'success' );
            } catch ( error ) {
                showProfileStatus( cfg().modelsError || cfg().cameraError, 'error' );
                throw error;
            }
        }

        async function startProfileVideo() {
            if ( ! frlProfileVideo ) { return; }
            try {
                frlProfileVideoStream = await navigator.mediaDevices.getUserMedia( {
                    video: { width: 640, height: 480, facingMode: 'user' }
                } );
                frlProfileVideo.srcObject = frlProfileVideoStream;
                await frlProfileVideo.play();
            } catch ( error ) {
                if ( error.name === 'NotAllowedError' ) {
                    showProfileStatus( cfg().permissionDenied, 'error' );
                } else {
                    showProfileStatus( cfg().cameraError, 'error' );
                }
            }
        }

        function stopProfileVideo() {
            if ( frlProfileVideoStream ) {
                frlProfileVideoStream.getTracks().forEach( function ( track ) { track.stop(); } );
                frlProfileVideoStream = null;
            }
        }

        function showProfileStatus( message, type ) {
            var statusEl = document.getElementById( 'frl-status-profile' );
            if ( statusEl ) {
                statusEl.textContent = message;
                statusEl.className = 'frl-status frl-status-' + ( type || 'info' );
            }
        }

        async function startProfileEnrollment() {
            if ( ! frlProfileModelsLoaded || ! frlProfileVideo ) { return; }

            frlProfileProcessing = true;
            frlProfileSamples    = [];
            var requiredSamples  = 15;
            showProfileStatus( cfg().detectingFace, 'info' );
            $( '#frl-enroll-progress-text-profile' ).text( '0/' + requiredSamples );

            var options = new faceapi.TinyFaceDetectorOptions( {
                inputSize: 416,
                scoreThreshold: 0.5
            } );

            var capture = async function () {
                if ( frlProfileSamples.length >= requiredSamples ) {
                    await completeProfileEnrollment();
                    return;
                }
                if ( ! frlProfileProcessing ) { return; }

                var result = await faceapi
                    .detectSingleFace( frlProfileVideo, options )
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if ( result ) {
                    frlProfileSamples.push( result.descriptor );
                    var current    = frlProfileSamples.length;
                    var percentage = ( current / requiredSamples ) * 100;
                    $( '#frl-enroll-progress-bar-profile' ).css( 'width', percentage + '%' );
                    $( '#frl-enroll-progress-text-profile' ).text( current + '/' + requiredSamples );
                    showProfileStatus( cfg().faceDetected + ' (' + current + ')', 'success' );
                }

                setTimeout( capture, 200 );
            };

            capture();
        }

        async function completeProfileEnrollment() {
            showProfileStatus( cfg().processing, 'info' );

            // Average the captured descriptors.
            var avgDescriptor = new Array( 128 ).fill( 0 );
            for ( var d of frlProfileSamples ) {
                for ( var i = 0; i < 128; i++ ) { avgDescriptor[ i ] += d[ i ]; }
            }
            for ( var k = 0; k < 128; k++ ) { avgDescriptor[ k ] /= frlProfileSamples.length; }

            // Resolve the target user id. The face section is
            // rendered for the user whose profile is being viewed
            // (the admin's own id on profile.php, but the OTHER
            // user's id on user-edit.php?user_id=X). We pass that
            // id to the server in the AJAX payload so the face
            // is saved against the correct owner. If the target
            // id is missing for any reason we fall back to the
            // current user id, which matches the previous
            // behaviour on profile.php.
            //
            // See handle_enroll_face() server-side for the
            // matching `edit_user` capability check.
            var profileCfg       = win.frlProfileConfig || {};
            var targetUserId     = parseInt( profileCfg.targetUserId, 10 ) || 0;
            var currentUserId    = parseInt( profileCfg.currentUserId, 10 ) || 0;
            var enrollmentUserId = targetUserId > 0 ? targetUserId : currentUserId;

            var formData = new FormData();
            formData.append( 'action',      'frl_enroll_face' );
            formData.append( 'nonce',       profileCfg.nonce );
            formData.append( 'descriptor',  JSON.stringify( Array.from( avgDescriptor ) ) );
            formData.append( 'device_name', getProfileDeviceName() );
            if ( enrollmentUserId > 0 ) {
                formData.append( 'user_id', String( enrollmentUserId ) );
            }

            try {
                var response = await fetch( win.frlProfileConfig.ajaxUrl, {
                    method: 'POST',
                    body: formData
                } );
                var data = await response.json();

                if ( data.success ) {
                    showProfileStatus( cfg().enrollmentComplete, 'success' );
                    frlProfileProcessing = false;
                    stopProfileVideo();
                    setTimeout( function () {
                        $( '#frl-enroll-modal-profile' ).hide();
                        location.reload();
                    }, 1500 );
                } else {
                    showProfileStatus(
                        data.data.message || cfg().enrollmentFailed,
                        'error'
                    );
                    frlProfileProcessing = true;
                }
            } catch ( error ) {
                showProfileStatus( cfg().enrollmentFailed, 'error' );
                frlProfileProcessing = true;
            }
        }

        function getProfileDeviceName() {
            var ua = navigator.userAgent;
            if ( /mobile|android|iphone|ipad|ipod/i.test( ua ) ) {
                if ( /ipad/i.test( ua ) )     { return 'iPad'; }
                if ( /iphone/i.test( ua ) )   { return 'iPhone'; }
                if ( /android/i.test( ua ) )  { return 'Android Device'; }
                return 'Mobile Device';
            }
            if ( /windows/i.test( ua ) ) { return 'Windows PC'; }
            if ( /mac/i.test( ua ) )     { return 'Mac'; }
            if ( /linux/i.test( ua ) )   { return 'Linux PC'; }
            return 'Unknown Device';
        }

        // Delete-face handler. Mirrors the licence-gated behaviour used
        // on the Enrolled Users admin page: if the premium licence is
        // not active, the JS short-circuits the request and shows the
        // same 'This is a premium feature' notice instead of asking
        // for confirmation and then silently failing. The server
        // (FRL_Premium_Gate::block_premium_ajax) is also wired up to
        // return a 403 with the same message as a safety net, and we
        // still detect that error code below for clients that bypass
        // the pre-check (e.g. a stale cached frlProfileConfig).
        $( document ).on( 'click', '.frl-delete-face-profile', function () {
            var $btn        = $( this );
            var faceId      = $btn.data( 'face-id' );
            var profileCfg  = win.frlProfileConfig || {};
            var isPremium   = profileCfg.isPremium !== false; // default true if absent
            var premiumMsg  = ( profileCfg.i18n && profileCfg.i18n.premiumMessage ) ||
                t(
                    'This is a premium feature. Please activate your license to use it.',
                    'This is a premium feature. Please activate your license to use it.'
                );

            // Self-deletion carve-out.
            //
            // The same delete button is rendered on both
            // `profile.php` (the user editing their OWN profile)
            // and `user-edit.php?user_id=X` (an admin editing
            // another user). A user (or admin) must always be
            // able to delete their own face - it is their own
            // biometric data - so we only apply the premium
            // gate to *cross-user* deletion. Self-deletion
            // (owner-id == current-user-id) is always allowed,
            // regardless of licence state. This mirrors the
            // server-side carve-out in FRL_Premium_Gate::
            // block_premium_ajax().
            var ownerId       = parseInt( $btn.data( 'owner-id' ), 10 ) || 0;
            var currentUserId = parseInt( profileCfg.currentUserId, 10 ) || 0;
            var isSelfDelete  = ownerId > 0 && currentUserId > 0 && ownerId === currentUserId;

            // License gate: only enforced for cross-user
            // deletion. A user deleting their own face skips
            // this check entirely.
            if ( ! isSelfDelete && ! isPremium ) {
                window.alert( premiumMsg );
                return;
            }

            var confirmMsg = t(
                'Are you sure you want to delete this face?',
                'Are you sure you want to delete this face?'
            );
            if ( ! window.confirm( confirmMsg ) ) {
                return;
            }

            var formData = new FormData();
            formData.append( 'action',  'frl_delete_face' );
            formData.append( 'nonce',   profileCfg.nonce );
            formData.append( 'face_id', faceId );
            // Include the user whose profile is currently
            // being rendered. The server uses this as a
            // trustworthy second signal for the self-deletion
            // carve-out: on `profile.php` this is always the
            // logged-in user, and the button is only rendered
            // for faces that belong to that user. We send
            // it so the gate can fall back to this value when
            // the face row's `user_id` column is stale (older
            // rows enrolled before the user-id-on-enrolment fix).
            var targetUserId = parseInt( profileCfg.targetUserId, 10 ) || 0;
            if ( targetUserId > 0 ) {
                formData.append( 'target_user_id', String( targetUserId ) );
            }

            $.ajax( {
                url:  profileCfg.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function ( data) {
                    if ( data.success ) {
                        location.reload();
                    } else {
                        // Treat the server-side premium gate as a first-
                        // class outcome so the user sees the same
                        // 'This is a premium feature' notice that the
                        // pre-check would have shown.
                        var code = data.data && data.data.code;
                        if ( 'frl_premium_required' === code ) {
                            window.alert( premiumMsg );
                            return;
                        }
                        var errMsg = ( data.data && data.data.message ) || t(
                            'Error deleting face',
                            'Error deleting face'
                        );
                        window.alert( errMsg );
                    }
                },
                error: function ( jqXHR ) {
                    // The premium gate returns HTTP 403 + a JSON body
                    // with { code: 'frl_premium_required', message: ... }
                    // when a non-self deletion is blocked. jQuery only
                    // routes the body to the `success` callback when the
                    // HTTP status is 2xx, so on 403 we have to parse it
                    // ourselves. We still fall back to the generic
                    // "Error deleting face" string for any other failure
                    // (network drop, 500, malformed JSON, etc.) so the
                    // user always gets a message.
                    var serverMsg = null;
                    var serverCode = null;
                    try {
                        var raw = jqXHR && jqXHR.responseText;
                        if ( raw && typeof raw === 'string' ) {
                            var parsed = JSON.parse( raw );
                            if ( parsed && parsed.data ) {
                                serverMsg = parsed.data.message;
                                serverCode = parsed.data.code;
                            }
                        }
                    } catch ( e ) {
                        // Body wasn't JSON; leave serverMsg null.
                    }
                    if ( 'frl_premium_required' === serverCode ) {
                        window.alert( premiumMsg );
                        return;
                    }
                    var generic = t( 'Error deleting face', 'Error deleting face' );
                    window.alert( serverMsg || generic );
                }
            } );
        } );
    }

    // Boot both flows; they are no-ops if the corresponding DOM is absent.
    $( function () {
        initPasswordFlow();
        initProfileFlow();

        // Auto-open the profile enrollment modal when the admin
        // notice links to profile.php?frl_open_enroll=1. The
        // delegated click handler above opens the modal, so a
        // synthetic click is the cleanest way to reuse it.
        try {
            var params = new URLSearchParams( window.location.search );
            if ( params.get( 'frl_open_enroll' ) === '1' ) {
                var btn = document.getElementById( 'frl-enroll-face-profile-btn' );
                if ( btn ) {
                    // Defer so the rest of the page (modal markup,
                    // face-api.js, etc.) has a chance to settle.
                    setTimeout( function () { btn.click(); }, 50 );
                }
            }
        } catch ( e ) {
            // URLSearchParams unavailable in very old browsers -
            // silent no-op so we never break the page.
        }
    } );

} )( jQuery );
