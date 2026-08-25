/**
 * Recognition - Admin Enrollment JavaScript
 *
 * @package Face_Recognition_Login
 */

(function($) {
    'use strict';

    var FRLAdminEnroll = {
        config: window.frlAdminConfig || {},
        modelsLoaded: false,
        isProcessing: false,
        videoStream: null,
        video: null,
        canvas: null,
        enrollmentSamples: [],
        selectedUserId: null,
        selectedUserName: null,
        selectedUserEmail: null,

        /**
         * Initialize enrollment functionality
         */
        init: function() {
            var self = this;
            
            this.video = document.getElementById('frl-admin-video');
            this.canvas = document.getElementById('frl-admin-canvas');
            
            // Bind events using jQuery
            $(document).on('click', '.frl-user-item', function(e) { self.selectUser(e); });
            $(document).on('input', '#frl-user-search', function(e) { self.filterUsers(e); });
            $(document).on('click', '#frl-start-enrollment-btn', function(e) { self.startEnrollment(e); });
            $(document).on('click', '#frl-cancel-enrollment', function(e) { self.cancelEnrollment(); });
            $(document).on('click', '#frl-enroll-another-btn', function(e) { self.enrollAnother(); });
            $(document).on('click', '#frl-change-user-btn', function(e) { self.changeUser(); });
        },

        /**
         * Select a user to enroll
         */
        selectUser: function(e) {
            var $item = $(e.currentTarget);
            var userId = $item.data('user-id');
            var userName = $item.find('.frl-user-name').text();
            var userEmail = $item.find('.frl-user-email').text();

            // Remove previous selection
            $('.frl-user-item').removeClass('selected');
            
            // Select this user
            $item.addClass('selected');
            this.selectedUserId = userId;
            this.selectedUserName = userName;
            this.selectedUserEmail = userEmail;

            // Show user info
            $('#frl-selected-user-info').show();
            $('#frl-selected-display').text(userName);
            $('#frl-selected-email').text(userEmail);
            $('#frl-selected-id').text(userId);

            // Show enrollment area
            $('#frl-no-user-selected').hide();
            $('#frl-enrollment-area').show();
        },

        /**
         * Filter users by search term
         */
        filterUsers: function(e) {
            var searchTerm = $(e.target).val().toLowerCase();
            
            $('.frl-user-item').each(function() {
                var $item = $(this);
                var name = $item.find('.frl-user-name').text().toLowerCase();
                var email = $item.find('.frl-user-email').text().toLowerCase();
                var login = $item.find('.frl-user-login').text().toLowerCase();

                if (name.indexOf(searchTerm) !== -1 || email.indexOf(searchTerm) !== -1 || login.indexOf(searchTerm) !== -1) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        },

        /**
         * Start face enrollment
         */
        startEnrollment: function(e) {
            var self = this;
            
            if (!this.selectedUserId) {
                alert(this.config.i18n.selectUser || 'Please select a user first');
                return;
            }

            if (this.isProcessing) {
                return;
            }

            this.isProcessing = true;
            this.enrollmentSamples = [];
            var requiredSamples = 15;

            this.showStatus(this.config.i18n.loadingModels || 'Loading face detection models...', 'info');
            this.showInstructions(this.config.i18n.detectingFace || 'Please wait...');
            this.updateProgress(0, requiredSamples);

            $('#frl-start-enrollment-btn').hide();
            $('#frl-cancel-enrollment').show();

            // First load models, then start video
            this.loadModels().then(function() {
                return self.startVideo();
            }).then(function() {
                self.beginCaptureLoop(requiredSamples);
            }).catch(function(error) {
                // Log error internally for debugging without exposing to console in production
                self.showStatus(error.message || self.config.i18n.modelsError || 'Failed to start', 'error');
                self.isProcessing = false;
                $('#frl-start-enrollment-btn').show();
                $('#frl-cancel-enrollment').hide();
            });
        },

        /**
         * Load face-api.js models
         */
        loadModels: function() {
            var self = this;
            var modelsUrl = this.config.modelsUrl || 'modelsUrl';

            return new Promise(function(resolve, reject) {
                if (typeof faceapi === 'undefined') {
                    reject(new Error('face-api.js library not loaded'));
                    return;
                }

                if (self.modelsLoaded) {
                    resolve();
                    return;
                }

                self.showStatus(self.config.i18n.initializing || 'Initializing...', 'info');

                Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(modelsUrl),
                    faceapi.nets.faceLandmark68Net.loadFromUri(modelsUrl),
                    faceapi.nets.faceRecognitionNet.loadFromUri(modelsUrl)
                ]).then(function() {
                    self.modelsLoaded = true;
                    self.showStatus(self.config.i18n.cameraReady || 'Camera ready', 'success');
                    resolve();
                }).catch(function(error) {
                    self.showStatus(self.config.i18n.cameraError || 'Model load error: ' + error.message, 'error');
                    reject(error);
                });
            });
        },

        /**
         * Start video stream
         */
        startVideo: function() {
            var self = this;
            
            return new Promise(function(resolve, reject) {
                if (!self.video) {
                    reject(new Error('Video element not found'));
                    return;
                }

                navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user'
                    }
                }).then(function(stream) {
                    self.videoStream = stream;
                    self.video.srcObject = stream;
                    
                    // Wait for video to be ready
                    self.video.onloadedmetadata = function() {
                        // Set canvas dimensions
                        if (self.canvas) {
                            self.canvas.width = self.video.videoWidth;
                            self.canvas.height = self.video.videoHeight;
                        }
                        
                        self.video.play().then(function() {
                            self.showStatus(self.config.i18n.cameraReady || 'Camera ready - position face', 'success');
                            resolve();
                        }).catch(function(error) {
                            reject(error);
                        });
                    };
                }).catch(function(error) {
                    if (error.name === 'NotAllowedError') {
                        self.showStatus(self.config.i18n.permissionDenied || 'Camera permission denied', 'error');
                    } else if (error.name === 'NotFoundError') {
                        self.showStatus('No camera found', 'error');
                    } else {
                        self.showStatus(self.config.i18n.cameraError || 'Camera error: ' + error.message, 'error');
                    }
                    reject(error);
                });
            });
        },

        /**
         * Begin the capture loop
         */
        beginCaptureLoop: function(requiredSamples) {
            var self = this;
            var options = new faceapi.TinyFaceDetectorOptions({
                inputSize: 416,
                scoreThreshold: 0.5
            });

            this.showStatus(this.config.i18n.detectingFace || 'Detecting face...', 'info');
            this.showInstructions(this.config.i18n.captureSamples || 'Position face and hold still...');

            function capture() {
                if (!self.isProcessing) {
                    return;
                }

                if (!self.video || !self.video.readyState || self.video.readyState < 2) {
                    setTimeout(capture, 100);
                    return;
                }

                faceapi
                    .detectSingleFace(self.video, options)
                    .withFaceLandmarks()
                    .withFaceDescriptor()
                    .then(function(result) {
                        if (result) {
                            self.enrollmentSamples.push(result.descriptor);
                            var current = self.enrollmentSamples.length;
                            var percentage = Math.round((current / requiredSamples) * 100);
                            self.showStatus('Capturing: ' + current + '/' + requiredSamples + ' (' + percentage + '%)', 'success');
                            self.updateProgress(current, requiredSamples);

                            // Draw face box on canvas
                            self.drawFaceBox(result.detection.box);

                            if (current >= requiredSamples) {
                                self.enrollmentComplete();
                            } else {
                                setTimeout(capture, 150);
                            }
                        } else {
                            self.showStatus(self.config.i18n.noFace || 'No face detected - position face in frame', 'info');
                            setTimeout(capture, 100);
                        }
                    })
                    .catch(function(error) {
                        setTimeout(capture, 200);
                    });
            }

            capture();
        },

        /**
         * Draw face detection box on canvas
         */
        drawFaceBox: function(box) {
            if (!this.canvas) return;
            
            var ctx = this.canvas.getContext('2d');
            ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            
            ctx.strokeStyle = '#00ff00';
            ctx.lineWidth = 2;
            ctx.strokeRect(box.x, box.y, box.width, box.height);
        },

        /**
         * Stop video stream
         */
        stopVideo: function() {
            if (this.videoStream) {
                this.videoStream.getTracks().forEach(function(track) {
                    track.stop();
                });
                this.videoStream = null;
            }
            if (this.video) {
                this.video.srcObject = null;
            }
        },

        /**
         * Show status message
         */
        showStatus: function(message, type) {
            $('#frl-enrollment-status').text(message).removeClass().addClass('frl-status frl-status-' + (type || 'info'));
        },

        /**
         * Show instructions
         */
        showInstructions: function(message) {
            $('#frl-enrollment-instructions').text(message);
        },

        /**
         * Update progress bar
         */
        updateProgress: function(current, total) {
            var percentage = Math.round((current / total) * 100);
            $('#frl-enroll-progress-bar').css('width', percentage + '%');
            $('#frl-enroll-progress-text').text(percentage + '%');
        },

        /**
         * Complete enrollment
         */
        enrollmentComplete: function() {
            var self = this;
            
            this.showStatus(this.config.i18n.processing || 'Processing...', 'info');
            this.showInstructions('');

            // Clear canvas
            if (this.canvas) {
                var ctx = this.canvas.getContext('2d');
                ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            }

            // Average all samples to create final descriptor
            var avgDescriptor = this.averageDescriptors(this.enrollmentSamples);

            // Send to server
            var formData = new FormData();
            formData.append('action', 'frl_admin_enroll_face');
            formData.append('nonce', this.config.nonce);
            formData.append('user_id', this.selectedUserId);
            formData.append('descriptor', JSON.stringify(avgDescriptor));

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    self.isProcessing = false;
                    self.stopVideo();

                    if (response.success) {
                        self.showEnrollmentSuccess();
                    } else {
                        self.showStatus(response.data && response.data.message || self.config.i18n.enrollmentFailed || 'Enrollment failed', 'error');
                        $('#frl-start-enrollment-btn').show();
                        $('#frl-cancel-enrollment').hide();
                    }
},
                error: function(xhr, status, error) {
                    self.showStatus(self.config.i18n.enrollmentFailed || 'Enrollment failed', 'error');
                    self.isProcessing = false;
                    $('#frl-start-enrollment-btn').show();
                    $('#frl-cancel-enrollment').hide();
                }
            });
        },

        /**
         * Average multiple descriptors
         */
        averageDescriptors: function(descriptors) {
            var avg = new Array(128).fill(0);

            for (var i = 0; i < descriptors.length; i++) {
                var descriptor = descriptors[i];
                for (var j = 0; j < 128; j++) {
                    avg[j] += descriptor[j];
                }
            }

            for (var k = 0; k < 128; k++) {
                avg[k] /= descriptors.length;
            }

            return avg;
        },

        /**
         * Show enrollment success state
         */
        showEnrollmentSuccess: function() {
            // Show success notice at the top
            var $notice = $('#frl-admin-success-notice');
            if ($notice.length) {
                $notice.show();
            }
            
            // Hide enrollment area
            $('#frl-enrollment-area').hide();
            
            // Show success state
            $('#frl-success-user-name').text(this.selectedUserName);
            $('#frl-success-user-email').text(this.selectedUserEmail);
            $('#frl-enrollment-success').show();
            
            // Scroll to top to show the success notice
            $('html, body').animate({
                scrollTop: 0
            }, 300);
            
            // Remove the enrolled user from the list dynamically
            var userItem = $('.frl-user-item[data-user-id="' + this.selectedUserId + '"]');
            if (userItem.length) {
                userItem.fadeOut(300, function() {
                    $(this).remove();
                    // Update count
                    var count = $('.frl-user-item:visible').length;
                    $('#frl-users-without-face-count').text('(' + count + ')');
                    
                    // Show empty state if no users left
                    if (count === 0) {
                        $('#frl-users-without-face-list').html(
                            '<div class="frl-empty-state">' +
                            '<div class="frl-empty-state-icon">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                            '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>' +
                            '<polyline points="22 4 12 14.01 9 11.01"/>' +
                            '</svg>' +
                            '</div>' +
                            '<p class="frl-empty-state-desc">All users have face enrolled!</p>' +
                            '</div>'
                        );
                    }
                });
            }
        },

        /**
         * Cancel enrollment
         */
        cancelEnrollment: function() {
            this.isProcessing = false;
            this.enrollmentSamples = [];
            this.stopVideo();
            
            // Clear canvas
            if (this.canvas) {
                var ctx = this.canvas.getContext('2d');
                ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            }
            
            this.showStatus('', 'info');
            this.showInstructions('Position the user\'s face within the frame and ensure good lighting.');
            this.updateProgress(0, 15);

            $('#frl-start-enrollment-btn').show();
            $('#frl-cancel-enrollment').hide();
        },

        /**
         * Enroll another user - reload page to get fresh user list
         */
        enrollAnother: function() {
            // Reload the page to get fresh data
            window.location.reload();
        },
        
        /**
         * Change user selection
         */
        changeUser: function() {
            // Reset to no user selected state
            $('.frl-user-item').removeClass('selected');
            this.selectedUserId = null;
            this.selectedUserName = null;
            this.selectedUserEmail = null;
            
            // Stop video if running
            this.stopVideo();
            
            // Reset UI
            $('#frl-enrollment-area').hide();
            $('#frl-no-user-selected').show();
            $('#frl-enrollment-success').hide();
            
            // Reset progress
            this.updateProgress(0, 15);
            this.showStatus('', 'info');
            this.showInstructions("Position the user's face within the frame and ensure good lighting.");
            
            $('#frl-start-enrollment-btn').show();
            $('#frl-cancel-enrollment').hide();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        FRLAdminEnroll.init();
    });

})(jQuery);
