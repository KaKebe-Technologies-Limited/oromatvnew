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

        function imageHandler() {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.click();

            input.onchange = function () {
                var file = input.files[0];
                if (!file) return;
                var formData = new FormData();
                formData.append('image', file);
                formData.append('csrf_token', window.OROMA_CSRF || '');

                fetch(window.OROMA_UPLOAD_URL, { method: 'POST', body: formData })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.url) {
                            var range = quill.getSelection(true);
                            quill.insertEmbed(range.index, 'image', data.url, 'user');
                            quill.insertText(range.index + 1, '\n', 'user');

                            var caption = window.prompt('Add a caption for this image (optional):', '');
                            caption = (caption || '').trim();
                            var cursor = range.index + 2;

                            if (caption) {
                                quill.insertText(cursor, caption, { italic: true }, 'user');
                                cursor += caption.length;
                                quill.insertText(cursor, '\n', 'user');
                                cursor += 1;
                            }

                            quill.setSelection(cursor);
                        } else {
                            alert(data.error || 'Image upload failed.');
                        }
                    })
                    .catch(function () { alert('Image upload failed.'); });
            };
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
