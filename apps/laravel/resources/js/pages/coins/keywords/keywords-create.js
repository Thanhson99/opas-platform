$(function () {
    const form = $('#keywordForm');
    const tagInput = $('#tagInput');
    const keywordInput = $('#keyword');
    const keywordList = $('#keywordList');
    const hiddenInputContainer = $('#hiddenTagInputs');

    let keywordTagId = null;

    /**
     * Add a new tag to list and hidden inputs
     * @param {string} tag - Tag text
     * @param {boolean} isKeyword - If this is the main keyword
     */
    function addTag(tag, isKeyword = false) {
        const tagId = 'tag-' + Date.now();

        const tagClass = isKeyword ? 'bg-dark' : 'bg-primary';
        const label = isKeyword ? tag : `#${tag}`;

        const tagBlock = $(`
            <div class="badge ${tagClass} text-white me-2 mb-2 px-3 py-2 rounded-pill d-inline-flex align-items-center fs-6" id="${tagId}">
                <button type="button" class="btn btn-sm text-white fw-bold px-1 py-0 me-2 remove-tag" data-tag-id="${tagId}" style="font-size: 1.2rem;">×</button>
                <span>${label}</span>
            </div>
        `);

        const hiddenInput = $(`<input type="hidden" name="tags[]" value="${tag}" data-tag-id="${tagId}">`);

        keywordList.prepend(tagBlock);
        hiddenInputContainer.prepend(hiddenInput);

        if (isKeyword) {
            keywordTagId = tagId;
        }
    }

    /**
     * Handle form submit: insert keyword if not in tags
     */
    form.on('submit', function (e) {
        e.preventDefault();

        const pendingTag = tagInput.val().trim();
        if (pendingTag) {
            addTag(pendingTag);
            tagInput.val('');
        }

        const keyword = keywordInput.val().trim();
        const exists = hiddenInputContainer.find(`input[name="tags[]"][value="${keyword}"]`).length > 0;

        if (keyword && !exists) {
            addTag(keyword, true);
        }

        this.submit();
    });

    /**
     * Handle Enter key in tag input
     */
    tagInput.on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const tag = tagInput.val().trim();
            if (tag) {
                addTag(tag);
                tagInput.val('');
            }
        }
    });

    /**
     * Remove tag
     */
    $(document).on('click', '.remove-tag', function () {
        const tagId = $(this).data('tag-id');
        $('#' + tagId).remove();
        $(`input[data-tag-id="${tagId}"]`).remove();

        // Reset
        if (tagId === keywordTagId) {
            keywordTagId = null;
        }
    });
});
