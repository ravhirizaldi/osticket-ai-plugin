<div id="ticket-askai">
    <h3>Ask AI (Model <?php echo Format::htmlchars($model); ?>)</h3>
    <b><a class="close" href="#"><i class="icon-remove-circle"></i></a></b>
    <hr />
    <p><strong>Question:</strong></p>
    <div id="askai-question-container" class="scrollable-content">
        <pre id="askai-question"><?php echo Format::htmlchars($question); ?></pre>
    </div>
    <button id="askai-send" class="button primary">Ask AI</button>
    <hr />
    <p><strong>AI Answer:</strong></p>
    <div id="askai-answer" class="ai-answer-container scrollable-content"></div>

    <style>
        .scrollable-content {
            max-height: 200px;
            /* Batasi tinggi maksimum */
            overflow-y: auto;
            /* Aktifkan scroll vertikal jika konten panjang */
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #f6f6f6;
            border-radius: 4px;
        }

        #askai-question-container pre {
            margin: 0;
            padding: 0;
            border: none;
            background: transparent;
        }

        .ai-answer-container {
            background-color: #fff;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 4px;
            margin-bottom: 10px;
            white-space: pre-line;
        }

        .ai-answer-container ol {
            list-style-type: decimal;
            padding-left: 20px;
            margin-bottom: 10px;
        }

        .ai-answer-container ol li {
            line-height: 1.6;
            margin-bottom: 5px;
        }

        .ai-answer-container p {
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .button.primary {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .button.primary:hover {
            background-color: #0056b3;
        }
    </style>
</div>

<script type="text/javascript">
    $(function() {
        $('#askai-send').click(function() {
            $('#askai-answer').html('<div class="loading">Please wait, We are fetching the AI response...</div>');
            $.ajax({
                url: 'ajax.php/askai/generate',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    question: $('#askai-question').text()
                }),
                success: function(res) {
                    $('#askai-answer').empty();
                    try {
                        if (Array.isArray(res)) {
                            let html = '';
                            res.forEach(solution => {
                                if (Array.isArray(solution.steps)) {
                                    html += '<ol>';
                                    solution.steps.forEach(step => {
                                        html += '<li>' + step + '</li>';
                                    });
                                    html += '</ol>';
                                }
                            });
                            $('#askai-answer').html(html || '<p>No steps provided.</p>');
                        } else if (res && res.answer) {
                            $('#askai-answer').html('<p>' + res.answer.replace(/\n/g, '<br>') + '</p>');
                        } else {
                            $('#askai-answer').html('<p>No valid response from AI.</p>');
                        }
                    } catch (e) {
                        $('#askai-answer').html('<p>?? Error parsing: ' + e + '</p>');
                        console.error('Parsing error:', e);
                    }
                },
                error: function(xhr) {
                    $('#askai-answer').html('<p>? Error: ' + xhr.responseText + '</p>');
                    console.error('AJAX error:', xhr.responseText);
                }
            });
        });
    });
</script>