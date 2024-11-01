jQuery(document).ready(function($) {
    var mediaUploader;

    $('#upload-media-button').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose logo',
            button: {
                text: 'Select Logo'
            },
            library: {
                type: 'image/png'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#chatgpt-plugin_logo').val(attachment.url);
        });

        mediaUploader.open();
    });
});
