jQuery(document).ready(function($) {
    $('#rq-generate-btn').on('click', function(e) {
        e.preventDefault();
        var total = parseInt($('#rq-total-count').text().replace(/,/g, ''));
        var current = parseInt($('#rq-current-count').text().replace(/,/g, ''));
        var $btn = $(this);
        var $bar = $('#rq-progress-bar-inner');
        var $status = $('#rq-generation-status');

        $btn.prop('disabled', true);
        $status.text('');
        function batch() {
            $.post(RQBenchmark.ajax_url, {
                action: 'generate_dummy_posts',
                nonce: RQBenchmark.nonce,
                current_count: current
            }, function(resp) {
                if (resp.success) {
                    current = resp.data.current_count;
                    $('#rq-current-count').text(current.toLocaleString());
                    var percent = Math.round((current/total)*100);
                    $bar.css('width', percent + '%');
                    $status.text('Added ' + current.toLocaleString() + ' / ' + total.toLocaleString() + ' posts...');
                    if (!resp.data.done) {
                        setTimeout(batch, 100);
                    } else {
                        $status.text('Generation complete!');
                        $btn.prop('disabled', false);
                    }
                } else {
                    $status.text('Error occurred.');
                    $btn.prop('disabled', false);
                }
            });
        }
        batch();
    });
});