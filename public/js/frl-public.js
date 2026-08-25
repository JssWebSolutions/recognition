/**
 * Face Recognition Login - Public JavaScript
 *
 * @package Face_Recognition_Login
 */

(function($) {
    'use strict';

    const FRL = {
        // Face-api.js state
        modelsLoaded: false,
        isProcessing: false,
        videoStream: null,
        video: null,
        canvas: null,
        enrollmentSamples: [],
        cameraOverlaysInjected: {}, // Track which modals got overlay chrome

        // Configuration
        config: window.frlConfig || {},

        /**
         * Initialize the plugin
         */
        init: function() {
            this.initLoginPage();
            this.initDashboard();
            this.bindEvents();
        },

        /**
         * Initialize login page functionality
         */
        initLoginPage: function() {
            if (!this.config.isLoginPage) return;

            this.video = document.getElementById('frl-video');
            this.canvas = document.getElementById('frl-canvas');
            this.modal = document.getElementById('frl-face-modal');

            if (!this.video || !this.modal) return;

            this.initModal();
        },

        /**
         * Initialize dashboard functionality
         */
        initDashboard: function() {
            if (typeof FRLDashboard !== 'undefined') return;

            // Check if we're on dashboard
            if ($('body').hasClass('frl-dashboard')) {
                this.loadFaces();
                this.loadLogs();
            }
        },

        /**
         * Bind global events
         */
        bindEvents: function() {
            // Face login button
            $(document).on('click', '#frl-face-login-btn', $.proxy(this.openLoginModal, this));
            
            // Start login button inside modal
            $(document).on('click', '#frl-start-login-btn', $.proxy(this.startLogin, this));

            // Modal close - login modal
            $(document).on('click', '#frl-face-modal .frl-close', $.proxy(function() {
                this.closeModal('frl-face-modal');
            }, this));

            // Modal close - enrollment modal
            $(document).on('click', '#frl-enroll-modal .frl-close', $.proxy(function() {
                this.closeModal('frl-enroll-modal');
            }, this));

            // Close modal on outside click
            $(document).on('click', '.frl-modal', function(e) {
                if (e.target === this) {
                    FRL.closeModal(this.id);
                }
            });

            // Close modal on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    const visibleModal = document.querySelector('.frl-modal[style*="block"], .frl-modal:not([style*="none"])');
                    if (visibleModal) {
                        FRL.closeModal(visibleModal.id);
                    }
                }
            });

            // Enroll button
            $(document).on('click', '#frl-enroll-btn', $.proxy(this.openEnrollModal, this));

            // Delete face
            $(document).on('click', '.frl-delete-face', $.proxy(this.deleteFace, this));

            // Export data
            $(document).on('click', '#frl-export-btn', $.proxy(this.exportData, this));
        },

        /**
         * Initialize modal
         */
        initModal: function() {
            if (this.modal) {
                this.modal.style.display = 'none';
            }
        },

        /**
         * Open login modal
         */
        async openLoginModal() {
            if (this.isProcessing) return;

            // Check HTTPS
            if (this.config.settings.requireHttps && !location.protocol.includes('https')) {
                this.showStatus(this.config.i18n.httpsRequired, 'error');
                return;
            }

            // Load models if needed
            await this.loadModels();

            // Open modal
            if (this.modal) {
                this.injectCameraOverlays('frl-video-container', { isLogin: true });
                this.modal.style.display = 'block';
            }

            // Start video
            await this.startVideo();

            // Start face detection and login process
            this.showStatus(this.config.i18n.detectingFace, 'info');
            $('#frl-start-login-btn').hide();
            this.startLogin();
        },

        /**
         * Open enrollment modal
         */
        async openEnrollModal() {
            if (this.isProcessing) return;

            const modal = document.getElementById('frl-enroll-modal');
            if (!modal) return;

            this.enrollmentVideo = document.getElementById('frl-enroll-video');
            this.enrollmentCanvas = document.getElementById('frl-enroll-canvas');
            this.enrollmentStatus = document.getElementById('frl-enroll-status');
            this.enrollmentInstructions = document.getElementById('frl-enroll-instructions');
            this.enrollmentProgress = document.getElementById('frl-enroll-progress-bar');

            // Load models if needed
            await this.loadModels();

            // Open modal
            this.injectCameraOverlays('frl-enroll-video-container', { isLogin: false });
            modal.style.display = 'block';

            // Start video
            await this.startVideo(this.enrollmentVideo);

            // Start enrollment
            await this.startEnrollment();
        },

        /**
         * Inject the premium camera overlay chrome into a video container:
         *  - 4 blue corner brackets
         *  - Top-left "Camera On" status pill (with animated dot)
         *  - Top-right camera-switch button (purely visual, no behavior change)
         *  - Bottom-center "Position your face here" position pill
         *  - Decorative face silhouette inside the dashed ring
         * Idempotent: safe to call multiple times for the same container.
         *
         * @param {string} containerId  Element id of the video container.
         * @param {Object} opts         { isLogin: boolean }
         */
        injectCameraOverlays(containerId, opts) {
            const container = document.getElementById(containerId);
            if (!container) return;
            if (this.cameraOverlaysInjected[containerId]) return;

            const isLogin = !!(opts && opts.isLogin);
            const i18n = (this.config && this.config.i18n) || {};

            // Face silhouette (decorative SVG inside the ring)
            const silhouette = document.createElement('div');
            silhouette.className = 'frl-face-silhouette';
            silhouette.setAttribute('aria-hidden', 'true');
            silhouette.innerHTML = `
                <svg viewBox="0 0 64 80" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="32" cy="26" r="14"/>
                    <path d="M10 76c0-12 10-22 22-22s22 10 22 22"/>
                </svg>
            `;
            container.appendChild(silhouette);

            // 4 blue corner brackets
            const brackets = document.createElement('div');
            brackets.className = 'frl-camera-brackets';
            brackets.setAttribute('aria-hidden', 'true');
            brackets.innerHTML = `
                <span class="frl-camera-bracket tl"></span>
                <span class="frl-camera-bracket tr"></span>
                <span class="frl-camera-bracket bl"></span>
                <span class="frl-camera-bracket br"></span>
            `;
            container.appendChild(brackets);

            // Top-left status pill ("Camera On")
            const statusPill = document.createElement('div');
            statusPill.className = 'frl-camera-status-pill';
            statusPill.setAttribute('role', 'status');
            statusPill.setAttribute('aria-live', 'polite');
            statusPill.innerHTML = `
                <span class="frl-pill-dot" aria-hidden="true"></span>
                <span class="frl-pill-text">${this.escapeHtml(i18n.cameraOn || 'Camera On')}</span>
            `;
            container.appendChild(statusPill);
            this.cameraOverlaysInjected[containerId + ':pill'] = statusPill;

            // Top-right camera-switch button (visual only, no behavior change)
            const switchBtn = document.createElement('button');
            switchBtn.type = 'button';
            switchBtn.className = 'frl-camera-switch-btn';
            switchBtn.setAttribute('aria-label', i18n.switchCamera || 'Switch camera');
            switchBtn.title = i18n.switchCamera || 'Switch camera';
            switchBtn.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M23 4v6h-6"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
            `;
            switchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleSwitchCamera();
            });
            container.appendChild(switchBtn);

            // Bottom-center position pill
            const positionPill = document.createElement('div');
            positionPill.className = 'frl-camera-position-pill';
            positionPill.setAttribute('aria-hidden', 'true');
            positionPill.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <circle cx="12" cy="10" r="3"/>
                    <path d="M7 20.7V21a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-.3"/>
                </svg>
                <span>${this.escapeHtml(i18n.positionFace || 'Position your face here')}</span>
            `;
            container.appendChild(positionPill);

            // Trust signals row, attached to the modal body right after the
            // video container. Only added for the login modal (the
            // enrollment modal already has its own stepper + instructions).
            if (isLogin) {
                const modal = this.modal || document.getElementById('frl-face-modal');
                if (modal) {
                    const body = modal.querySelector('.frl-modal-body');
                    if (body && !body.querySelector('.frl-trust-row')) {
                        const trust = document.createElement('div');
                        trust.className = 'frl-trust-row';
                        trust.setAttribute('aria-label', i18n.trustHeading || 'Why this is safe');
                        trust.innerHTML = `
                            <div class="frl-trust-item">
                                <span class="frl-trust-item-icon is-blue" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <span class="frl-trust-item-label">${this.escapeHtml(i18n.trustSecure || 'Secure')}</span>
                            </div>
                            <div class="frl-trust-item">
                                <span class="frl-trust-item-icon is-green" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </span>
                                <span class="frl-trust-item-label">${this.escapeHtml(i18n.trustPrivate || 'Private')}</span>
                            </div>
                            <div class="frl-trust-item">
                                <span class="frl-trust-item-icon is-purple" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                </span>
                                <span class="frl-trust-item-label">${this.escapeHtml(i18n.trustFast || 'Fast')}</span>
                            </div>
                            <div class="frl-trust-item">
                                <span class="frl-trust-item-icon is-amber" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </span>
                                <span class="frl-trust-item-label">${this.escapeHtml(i18n.trustPasswordless || 'Passwordless')}</span>
                            </div>
                        `;
                        // Place trust row right after the video container.
                        if (container.parentNode) {
                            container.parentNode.insertBefore(trust, container.nextSibling);
                        } else {
                            body.appendChild(trust);
                        }
                    }
                }
            }

            this.cameraOverlaysInjected[containerId] = true;
        },

        /**
         * Update the camera status pill text + state class.
         * States: is-off, is-warning, is-error. Default = "on" (green dot).
         * @param {string} containerId
         * @param {string} text
         * @param {string} state  '' | 'is-off' | 'is-warning' | 'is-error'
         */
        updateCameraStatusPill(containerId, text, state) {
            const pill = this.cameraOverlaysInjected[containerId + ':pill'];
            if (!pill) return;
            const label = pill.querySelector('.frl-pill-text');
            if (label && text) label.textContent = text;
            pill.classList.remove('is-off', 'is-warning', 'is-error');
            if (state) pill.classList.add(state);
        },

        /**
         * Handle click on the decorative camera-switch button. The actual
         * getUserMedia stream is the same one we already opened, so we
         * simply toggle a "switched" state, show a status message, and
         * allow the user to dismiss it. (Real camera flipping is browser-
         * dependent and out of scope for this UX enhancement.)
         */
        handleSwitchCamera() {
            if (!this.isProcessing) return;
this.showStatus(this.config.i18n.switchingCamera || 'Switching camera…', 'info');
            this.updateCameraStatusPill('frl-video-container', this.config.i18n.switchingCamera || 'Switching…', 'is-warning');
            setTimeout(() => {
                this.showStatus(this.config.i18n.cameraReady || 'Camera ready', 'success');
                this.updateCameraStatusPill('frl-video-container', this.config.i18n.cameraOn || 'Camera On', '');
            }, 900);
        },

        /**
         * Tiny HTML-escape helper used by the overlay injection code.
         * @param {string} str
         * @returns {string}
         */
        escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        /**
         * Close modal
         */
        closeModal(modalId) {
            // Determine which modal to close
            let modal = null;
            
            if (modalId) {
                modal = document.getElementById(modalId);
            } else if (this.modal) {
                modal = this.modal;
            } else {
                // Try to find any visible modal
                modal = document.querySelector('.frl-modal[style*="block"]');
                if (!modal) {
                    modal = document.getElementById('frl-face-modal');
                }
                if (!modal) {
                    modal = document.getElementById('frl-enroll-modal');
                }
            }
            
            if (modal) {
                modal.style.display = 'none';
            }
            
            // Stop video stream
            this.stopVideo();
            
            // Reset processing state
            this.isProcessing = false;
        },

        /**
         * Load face-api.js models
         */
        async loadModels() {
            if (this.modelsLoaded) return;

            this.showStatus(this.config.i18n.initializing, 'info');

            try {
                // Load TinyFaceDetector model
                await faceapi.nets.tinyFaceDetector.loadFromUri(this.config.modelsUrl);

                // Load Face Landmark Model
                await faceapi.nets.faceLandmark68Net.loadFromUri(this.config.modelsUrl);

                // Load Face Recognition Model
                await faceapi.nets.faceRecognitionNet.loadFromUri(this.config.modelsUrl);

                this.modelsLoaded = true;
                this.showStatus(this.config.i18n.cameraReady, 'success');
            } catch (error) {
                this.showStatus(this.config.i18n.cameraError, 'error');
                throw error;
            }
        },

        /**
         * Start video stream
         */
        async startVideo(videoEl) {
            const video = videoEl || this.video;
            if (!video) return;

            try {
                this.videoStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: 640,
                        height: 480,
                        facingMode: 'user'
                    }
                });

                video.srcObject = this.videoStream;
                await video.play();
            } catch (error) {
                if (error.name === 'NotAllowedError') {
                    this.showStatus(this.config.i18n.permissionDenied, 'error');
                } else {
                    this.showStatus(this.config.i18n.cameraError, 'error');
                }
            }
        },

        /**
         * Stop video stream
         */
        stopVideo() {
            if (this.videoStream) {
                this.videoStream.getTracks().forEach(track => track.stop());
                this.videoStream = null;
            }
        },

        /**
         * Show status message
         */
        showStatus(message, type) {
            const statusEl = document.getElementById('frl-status');
            if (statusEl) {
                statusEl.textContent = message;
                statusEl.className = 'frl-status frl-status-' + (type || 'info');
            }
            // Keep the camera viewport's top-left status pill in sync with
            // the underlying status so the user always knows whether the
            // camera is currently healthy / warning / errored. Pill text
            // stays short (truncated if needed by CSS) to avoid overlap.
            if (this.cameraOverlaysInjected['frl-video-container']) {
                let pillState = '';
                let pillText = this.config.i18n.cameraOn || 'Camera On';
                if (type === 'warning') { pillState = 'is-warning'; pillText = this.config.i18n.cameraAdjusting || 'Adjusting…'; }
                else if (type === 'error') { pillState = 'is-error'; pillText = this.config.i18n.cameraError || 'Camera error'; }
                this.updateCameraStatusPill('frl-video-container', pillText, pillState);
            }
        },

        /**
         * Start face login process
         */
        async startLogin() {
            if (!this.modelsLoaded || !this.video) return;

            this.isProcessing = true;
            this.showStatus(this.config.i18n.detectingFace, 'info');

            // Create canvas overlay
            const displaySize = { width: this.video.videoWidth, height: this.video.videoHeight };
            this.canvas.width = displaySize.width;
            this.canvas.height = displaySize.height;

            const options = new faceapi.TinyFaceDetectorOptions({
                inputSize: 416,
                scoreThreshold: 0.5
            });

            // Detection loop
            const detect = async () => {
                if (!this.isProcessing) return;

                const result = await faceapi
                    .detectSingleFace(this.video, options)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (result) {
                    this.isProcessing = false;
                    this.showStatus(this.config.i18n.faceDetected, 'success');

                    // Optionally run liveness check
                    if (this.config.settings.livenessDetection) {
                        const livenessPassed = await this.checkLiveness();
                        if (!livenessPassed) {
                            this.showStatus(this.config.i18n.livenessCheck, 'error');
                            this.isProcessing = true;
                            setTimeout(detect, 500);
                            return;
                        }
                    }

                    // Authenticate
                    await this.authenticate(result.descriptor);
                } else {
                    this.showStatus(this.config.i18n.noFace, 'warning');
                    setTimeout(detect, 100);
                }
            };

            detect();
        },

        /**
         * Check liveness (basic blink detection)
         */
        async checkLiveness() {
            // Simple implementation - check for eye closure
            const options = new faceapi.TinyFaceDetectorOptions({
                inputSize: 224,
                scoreThreshold: 0.5
            });

            let blinkCount = 0;
            let lastBlinkTime = 0;
            const eyeDistanceThreshold = 0.25;

            for (let i = 0; i < 30; i++) {
                const result = await faceapi
                    .detectSingleFace(this.video, options)
                    .withFaceLandmarks();

                if (result) {
                    const landmarks = result.landmarks;
                    const leftEye = landmarks.getLeftEye();
                    const rightEye = landmarks.getRightEye();

                    // Calculate eye aspect ratio (simplified)
                    const leftEAR = this.calculateEAR(leftEye);
                    const rightEAR = this.calculateEAR(rightEye);
                    const avgEAR = (leftEAR + rightEAR) / 2;

                    const now = Date.now();

                    if (avgEAR < eyeDistanceThreshold && (now - lastBlinkTime) > 200) {
                        blinkCount++;
                        lastBlinkTime = now;
                    }
                }

                await new Promise(r => setTimeout(r, 100));
            }

            return blinkCount >= 1;
        },

        /**
         * Calculate Eye Aspect Ratio
         */
        calculateEAR(eye) {
            // Vertical distances
            const v1 = this.distance(eye[1], eye[5]);
            const v2 = this.distance(eye[2], eye[4]);

            // Horizontal distance
            const h = this.distance(eye[0], eye[3]);

            return (v1 + v2) / (2.0 * h);
        },

        /**
         * Calculate distance between two points
         */
        distance(p1, p2) {
            return Math.sqrt(Math.pow(p1.x - p2.x, 2) + Math.pow(p1.y - p2.y, 2));
        },

        /**
         * Authenticate with descriptor
         */
        async authenticate(descriptor) {
            this.showStatus(this.config.i18n.authenticating, 'info');

            const formData = new FormData();
            formData.append('action', 'frl_authenticate');
            formData.append('nonce', this.config.nonce);
            formData.append('descriptor', JSON.stringify(Array.from(descriptor)));
            formData.append('redirect_to', this.getRedirectParam());

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    this.showStatus(this.config.i18n.success, 'success');
                    
                    // Stop video before redirect
                    this.stopVideo();

                    // Redirect - be more robust with redirect handling
                    let redirectUrl = data.data.redirect_to;
                    
                    // Fallback if redirect_to is empty or invalid - use config adminUrl
                    if (!redirectUrl || redirectUrl === '' || redirectUrl.indexOf('wp-login') !== -1) {
                        redirectUrl = this.config.adminUrl || window.location.origin + '/wp-admin/';
                    }
                    
                    // Close modal and redirect with small delay for visual feedback
                    this.closeModal();
                    
                    // Use setTimeout to ensure redirect happens after UI updates
                    setTimeout(function() {
                        window.location.href = redirectUrl;
                    }, 300);
                } else {
                    this.showStatus(data.data.message || this.config.i18n.failed, 'error');
                    this.isProcessing = true;
                }
            } catch (error) {
                this.showStatus(this.config.i18n.failed, 'error');
                this.isProcessing = true;
            }
        },

        /**
         * Get redirect parameter
         */
        getRedirectParam() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('redirect_to') || '';
        },

        /**
         * Start face enrollment
         */
        async startEnrollment() {
            if (!this.modelsLoaded || !this.enrollmentVideo) return;

            this.isProcessing = true;
            this.enrollmentSamples = [];
            const requiredSamples = 15;

            this.showEnrollmentStatus(0, requiredSamples);
            this.updateEnrollmentProgress(0, requiredSamples);

            const options = new faceapi.TinyFaceDetectorOptions({
                inputSize: 416,
                scoreThreshold: 0.5
            });

            const capture = async () => {
                if (this.enrollmentSamples.length >= requiredSamples) {
                    // Enrollment complete
                    this.enrollmentComplete();
                    return;
                }

                if (!this.isProcessing) return;

                const result = await faceapi
                    .detectSingleFace(this.enrollmentVideo, options)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (result) {
                    this.enrollmentSamples.push(result.descriptor);
                    const current = this.enrollmentSamples.length;
                    this.showEnrollmentStatus(current, requiredSamples);
                    this.updateEnrollmentProgress(current, requiredSamples);
                }

                setTimeout(capture, 200);
            };

            this.showEnrollmentInstructions(this.config.i18n.captureSamples);
            capture();
        },

        /**
         * Show enrollment status
         */
        showEnrollmentStatus(current, total) {
            if (this.enrollmentStatus) {
                this.enrollmentStatus.textContent = 
                    `${this.config.i18n.processing} ${current}/${total}`;
                this.enrollmentStatus.className = 'frl-status frl-status-info';
            }
        },

        /**
         * Show enrollment instructions
         */
        showEnrollmentInstructions(message) {
            if (this.enrollmentInstructions) {
                this.enrollmentInstructions.textContent = message;
            }
        },

        /**
         * Update enrollment progress bar
         */
        updateEnrollmentProgress(current, total) {
            const percentage = Math.min(100, Math.max(0, (current / total) * 100));
            if (this.enrollmentProgress) {
                this.enrollmentProgress.style.width = percentage + '%';
            }
            // Also update the text element if present (#frl-enroll-progress-text)
            const progressText = document.getElementById('frl-enroll-progress-text');
            if (progressText) {
                progressText.textContent = Math.round(percentage) + '%';
            }
        },

        /**
         * Complete enrollment
         */
        async enrollmentComplete() {
            this.showEnrollmentInstructions(this.config.i18n.processing);

            // Average all samples to create final descriptor
            const avgDescriptor = this.averageDescriptors(this.enrollmentSamples);

            const formData = new FormData();
            formData.append('action', 'frl_enroll_face');
            formData.append('nonce', this.config.nonce);
            formData.append('descriptor', JSON.stringify(Array.from(avgDescriptor)));
            formData.append('device_name', this.getDeviceName());

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    this.showEnrollmentStatus(100, 100);
                    this.showEnrollmentInstructions(this.config.i18n.enrollmentComplete);
                    this.isProcessing = false;

                    // Close modal and redirect
                    setTimeout(() => {
                        this.closeModal('frl-enroll-modal');
                        
                        // If on registration page, redirect to wp-admin
                        if (this.config.isRegistrationPage) {
                            // Set cookie to indicate enrollment completed, then redirect
                            document.cookie = "frl_just_enrolled=1; path=/; max-age=3600";
                            // Small delay for visual feedback, then reload
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            this.loadFaces();
                        }
                    }, 1000);
                } else {
                    this.showEnrollmentInstructions(data.data.message || this.config.i18n.enrollmentFailed);
                    this.isProcessing = true;
                }
            } catch (error) {
                this.showEnrollmentInstructions(this.config.i18n.enrollmentFailed);
                this.isProcessing = true;
            }
        },

        /**
         * Average multiple descriptors
         */
        averageDescriptors(descriptors) {
            const avg = new Array(128).fill(0);

            for (const descriptor of descriptors) {
                for (let i = 0; i < 128; i++) {
                    avg[i] += descriptor[i];
                }
            }

            for (let i = 0; i < 128; i++) {
                avg[i] /= descriptors.length;
            }

            return avg;
        },

        /**
         * Get device name
         */
        getDeviceName() {
            const ua = navigator.userAgent;
            if (/mobile|android|iphone|ipad|ipod/i.test(ua)) {
                if (/ipad/i.test(ua)) return 'iPad';
                if (/iphone/i.test(ua)) return 'iPhone';
                if (/android/i.test(ua)) return 'Android Device';
                return 'Mobile Device';
            }
            if (/windows/i.test(ua)) return 'Windows PC';
            if (/mac/i.test(ua)) return 'Mac';
            if (/linux/i.test(ua)) return 'Linux PC';
            return 'Unknown Device';
        },

        /**
         * Wait for a video element to be ready (has dimensions and frames).
         * Resolves with the video's display size, rejects on timeout.
         */
        waitForVideoReady(video, timeoutMs = 10000) {
            return new Promise((resolve, reject) => {
                if (!video) {
                    reject(new Error('No video element'));
                    return;
                }

                // If video is already ready with dimensions, resolve immediately
                if (video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0) {
                    resolve({ width: video.videoWidth, height: video.videoHeight });
                    return;
                }

                let settled = false;
                const cleanup = () => {
                    video.removeEventListener('loadedmetadata', onReady);
                    video.removeEventListener('canplay', onReady);
                    video.removeEventListener('playing', onReady);
                    clearInterval(pollTimer);
                    clearTimeout(timeoutTimer);
                };

                const onReady = () => {
                    if (settled) return;
                    // Wait one more frame to make sure dimensions are available
                    requestAnimationFrame(() => {
                        if (video.videoWidth > 0 && video.videoHeight > 0) {
                            settled = true;
                            cleanup();
                            resolve({ width: video.videoWidth, height: video.videoHeight });
                        }
                    });
                };

                // Poll for videoWidth/videoHeight to be set (browser quirks)
                const pollTimer = setInterval(() => {
                    if (video.videoWidth > 0 && video.videoHeight > 0) {
                        onReady();
                    }
                }, 100);

                video.addEventListener('loadedmetadata', onReady);
                video.addEventListener('canplay', onReady);
                video.addEventListener('playing', onReady);

                const timeoutTimer = setTimeout(() => {
                    if (settled) return;
                    settled = true;
                    cleanup();
                    // Resolve anyway with fallback dimensions so detection can still try
                    resolve({ width: video.videoWidth || 640, height: video.videoHeight || 480 });
                }, timeoutMs);
            });
        },

        /**
         * Initialize registration enrollment flow
         * Called from registration-page.php when user clicks "Enroll My Face"
         */
        initRegistrationEnrollment: async function() {
            if (this.isProcessing) return;

            // Use enrollment video element if available
            this.enrollmentVideo = document.getElementById('frl-enroll-video');
            this.enrollmentCanvas = document.getElementById('frl-enroll-canvas');
            this.enrollmentStatus = document.getElementById('frl-enroll-status');
            this.enrollmentInstructions = document.getElementById('frl-enroll-instructions');
            this.enrollmentProgress = document.getElementById('frl-enroll-progress-bar');

            if (!this.enrollmentVideo) {
                return;
            }

            // Make sure progress bar starts at 0% and text shows 0%
            if (this.enrollmentProgress) {
                this.enrollmentProgress.style.width = '0%';
            }
            const progressText = document.getElementById('frl-enroll-progress-text');
            if (progressText) {
                progressText.textContent = '0%';
            }

            try {
                // Show initializing status in the modal-specific status element
                this.showEnrollmentStatus(0, 15);
                this.showEnrollmentInstructions(this.config.i18n.initializing || 'Initializing...');

                // Ensure models are loaded
                await this.loadModels();

                // Start video
                await this.startVideo(this.enrollmentVideo);

                // Wait for the video to actually have dimensions / frames
                // Without this, face-api.js can't detect anything
await this.waitForVideoReady(this.enrollmentVideo, 10000);

                // Start enrollment process
                await this.startEnrollment();
            } catch (error) {
                this.showEnrollmentInstructions(
                    this.config.i18n.cameraError || 'Camera initialization failed. Please refresh and try again.'
                );
                this.isProcessing = false;
            }
        },

        /**
         * Load user's face profiles
         */
        async loadFaces() {
            const container = document.getElementById('frl-faces-container');
            if (!container) return;

            const formData = new FormData();
            formData.append('action', 'frl_get_faces');
            formData.append('nonce', this.config.nonce);

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.data.faces.length > 0) {
                    let html = '<table class="widefat"><thead><tr>';
                    html += '<th>Device</th>';
                    html += '<th>Created</th>';
                    html += '<th>Last Used</th>';
                    html += '<th>Actions</th>';
                    html += '</tr></thead><tbody>';

                    for (const face of data.data.faces) {
                        html += '<tr>';
                        html += '<td>' + face.device_name + '</td>';
                        html += '<td>' + face.created_at + '</td>';
                        html += '<td>' + (face.last_used || 'Never') + '</td>';
                        html += '<td><button type="button" class="button frl-delete-face" data-face-id="' + face.id + '">Delete</button></td>';
                        html += '</tr>';
                    }

                    html += '</tbody></table>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p>' + this.config.i18n.noFace + '</p>';
                }
            } catch (error) {
                container.innerHTML = '<p>Error loading faces.</p>';
            }
        },

        /**
         * Load authentication logs
         */
        async loadLogs() {
            const container = document.getElementById('frl-logs-container');
            if (!container) return;

            const formData = new FormData();
            formData.append('action', 'frl_get_logs');
            formData.append('nonce', this.config.nonce);
            formData.append('limit', 10);

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.data.logs.length > 0) {
                    let html = '<table class="widefat"><thead><tr>';
                    html += '<th>Result</th>';
                    html += '<th>Time</th>';
                    html += '<th>IP Address</th>';
                    html += '</tr></thead><tbody>';

                    for (const log of data.data.logs) {
                        html += '<tr>';
                        html += '<td>' + (log.result === 'success' ? '<span style="color:green">Success</span>' : '<span style="color:red">Failed</span>') + '</td>';
                        html += '<td>' + log.time_ago + '</td>';
                        html += '<td>' + (log.ip_address || '-') + '</td>';
                        html += '</tr>';
                    }

                    html += '</tbody></table>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p>No authentication history.</p>';
                }
            } catch (error) {
                container.innerHTML = '<p>Error loading history.</p>';
            }
        },

        /**
         * Delete face profile
         */
        async deleteFace(e) {
            const faceId = $(e.target).data('face-id');
            if (!confirm('Are you sure you want to delete this face profile?')) return;

            const formData = new FormData();
            formData.append('action', 'frl_delete_face');
            formData.append('nonce', this.config.nonce);
            formData.append('face_id', faceId);

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    this.loadFaces();
                } else {
                    alert(data.data.message || 'Error deleting face');
                }
            } catch (error) {
                alert('Error deleting face');
            }
        },

        /**
         * Export user data
         */
        async exportData() {
            const formData = new FormData();
            formData.append('action', 'frl_export_data');
            formData.append('nonce', this.config.nonce);

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Create and download JSON file
                    const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'face-login-export-' + new Date().toISOString().split('T')[0] + '.json';
                    a.click();
                    URL.revokeObjectURL(url);
                } else {
                    alert(data.data.message || 'Error exporting data');
                }
            } catch (error) {
                alert('Error exporting data');
            }
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        FRL.init();
    });

    // Expose globally
    window.FRL = FRL;

})(jQuery);
