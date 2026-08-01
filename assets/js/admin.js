(function () {
    'use strict';

    // ---------- sidebar toggle (mobile) ----------
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('adminSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    // ---------- auto-fill slug from title ----------
    var titleInput = document.getElementById('title');
    var slugInput = document.getElementById('slug');
    if (titleInput && slugInput) {
        var slugTouched = slugInput.value.trim() !== '';
        slugInput.addEventListener('input', function () { slugTouched = true; });
        titleInput.addEventListener('input', function () {
            if (slugTouched) return;
            slugInput.value = titleInput.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });
    }

    // ---------- featured image preview ----------
    var imageInput = document.getElementById('featured_image_file');
    var imagePreview = document.getElementById('imagePreviewImg');
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function () {
            var file = imageInput.files && imageInput.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                imagePreview.src = e.target.result;
                imagePreview.closest('.image-preview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    }

    // ---------- Quill rich text editor ----------
    var editorEl = document.getElementById('editor');
    var contentField = document.getElementById('content');
    if (editorEl && window.Quill) {
        var quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Write the article…',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'code-block', 'link', 'image'],
                        ['clean']
                    ],
                    handlers: { image: imageHandler }
                }
            }
        });

        if (contentField && contentField.value) {
            quill.clipboard.dangerouslyPasteHTML(contentField.value);
        }

        var form = editorEl.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                contentField.value = quill.root.innerHTML;
            });
        }

        var imgChoiceOverlay = null;
        function getImgChoiceOverlay() {
            if (imgChoiceOverlay) return imgChoiceOverlay;

            var overlay = document.createElement('div');
            overlay.className = 'img-choice-overlay';
            overlay.innerHTML =
                '<div class="img-choice-modal">' +
                    '<h3>Insert Image</h3>' +
                    '<p>How many images do you want to insert?</p>' +
                    '<div class="img-choice-actions">' +
                        '<button type="button" class="img-choice-btn" data-choice="single"><i class="fas fa-image"></i> Single image</button>' +
                        '<button type="button" class="img-choice-btn" data-choice="double"><i class="fas fa-images"></i> Two images, side by side</button>' +
                    '</div>' +
                    '<button type="button" class="img-choice-cancel">Cancel</button>' +
                '</div>';
            document.body.appendChild(overlay);

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay || e.target.classList.contains('img-choice-cancel')) {
                    closeImgChoice();
                    return;
                }
                var btn = e.target.closest('.img-choice-btn');
                if (!btn) return;
                closeImgChoice();
                // Fire the upload from inside this click handler, synchronously,
                // so the file-picker dialog still counts as user-initiated.
                if (btn.dataset.choice === 'double') {
                    uploadDouble();
                } else {
                    uploadSingle();
                }
            });

            imgChoiceOverlay = overlay;
            return overlay;
        }

        function closeImgChoice() {
            if (imgChoiceOverlay) imgChoiceOverlay.classList.remove('is-open');
        }

        function imageHandler() {
            getImgChoiceOverlay().classList.add('is-open');
        }

        function uploadSingle() {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.click();
            input.onchange = function () {
                var file = input.files[0];
                if (!file) return;
                doUpload(file, function(url) {
                    var range = quill.getSelection(true);
                    quill.insertEmbed(range.index, 'image', url, 'user');
                    quill.insertText(range.index + 1, '\n', 'user');
                    quill.setSelection(range.index + 2);
                });
            };
        }

        function uploadDouble() {
            // Pick first image
            var input1 = document.createElement('input');
            input1.setAttribute('type', 'file');
            input1.setAttribute('accept', 'image/*');
            input1.click();
            input1.onchange = function () {
                var file1 = input1.files[0];
                if (!file1) return;
                // Pick second image
                var input2 = document.createElement('input');
                input2.setAttribute('type', 'file');
                input2.setAttribute('accept', 'image/*');
                input2.click();
                input2.onchange = function () {
                    var file2 = input2.files[0];
                    if (!file2) return;
                    // Upload both then insert side-by-side HTML
                    doUpload(file1, function(url1) {
                        doUpload(file2, function(url2) {
                            var range = quill.getSelection(true);
                            var html = '<div class="img-row">'
                                + '<img src="' + url1 + '" alt="" />'
                                + '<img src="' + url2 + '" alt="" />'
                                + '</div>';
                            quill.clipboard.dangerouslyPasteHTML(range.index, html, 'user');
                            quill.insertText(range.index + 1, '\n', 'user');
                            quill.setSelection(range.index + 2);
                        });
                    });
                };
            };
        }

        function doUpload(file, callback) {
            var formData = new FormData();
            formData.append('image', file);
            formData.append('csrf_token', window.OROMA_CSRF || '');
            fetch(window.OROMA_UPLOAD_URL, { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.url) {
                        callback(data.url);
                    } else {
                        alert(data.error || 'Image upload failed.');
                    }
                })
                .catch(function() { alert('Image upload failed.'); });
        }
    }

    // ---------- confirm-before-delete forms ----------
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
})();
