jQuery(document).ready(function($) {
    // Test TTS Generation on Tools Page
    $('#test-tts').on('click', function() {
        const button = $(this);
        const testText = $('#test-text').val();
        const resultDiv = $('#test-result');

        if (!testText.trim()) {
            resultDiv.html('<p style="color: red;">Please enter some text to generate audio.</p>');
            return;
        }

        button.prop('disabled', true).text('Generating...');
        resultDiv.html('<p>Processing...</p>');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_tts_test_provider',
                text: testText,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    let audioPlayer = '<p>Test audio generated successfully:</p>';
                    audioPlayer += '<audio controls src="' + response.data.audio_url + '"></audio>';
                    if(response.data.provider) {
                        audioPlayer += '<p><small>Provider used: ' + response.data.provider + '</small></p>';
                    }
                    resultDiv.html(audioPlayer);
                } else {
                    let errorMessage = '<p style="color: red;">Error: ' + (response.data.message || 'Unknown error occurred.') + '</p>';
                    if (response.data.error_details) {
                        errorMessage += '<p style="color: red;"><small>Details: ' + response.data.error_details + '</small></p>';
                    }
                    resultDiv.html(errorMessage);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                resultDiv.html('<p style="color: red;">AJAX Error: ' + textStatus + ' - ' + errorThrown + '</p>');
            },
            complete: function() {
                button.prop('disabled', false).text('Generate Test Audio');
            }
        });
    });

    // TTS Provider Toggle Functionality
    $('.tts-provider-toggle').on('change', function() {
        const isEnabled = $(this).is(':checked');
        const providerField = $(this).closest('.tts-provider-field');

        const configSections = providerField.find('.tts-provider-config')
                              .add(providerField.siblings().find('.tts-provider-config'));

        if (isEnabled) {
            configSections.css('opacity', '1');
            configSections.find('input, select, textarea').prop('disabled', false);
        } else {
            configSections.css('opacity', '0.5');
            configSections.find('input, select, textarea').prop('disabled', true);
        }
    });

    // Initialize the state on page load
    $('.tts-provider-toggle').trigger('change');

    // Auto-save player settings
    $('.tts-player-setting').on('change', function() {
        const $field = $(this);

        const playerSettings = {};
        $('.tts-player-setting').each(function() {
            const settingName = $(this).data('setting');
            const settingValue = $(this).is(':checkbox') ? ($(this).is(':checked') ? '1' : '0') : $(this).val();
            playerSettings[settingName] = settingValue;
        });

        playerSettings.nonce = wpTtsAdmin.nonce;
        playerSettings.action = 'tts_save_player_config';

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: playerSettings,
            success: function(response) {
                if (response.success) {
                    $field.css('background-color', '#d4edda').delay(1000).queue(function() {
                        $(this).css('background-color', '').dequeue();
                    });
                } else {
                    alert('Error saving settings: ' + response.data.message);
                }
            },
            error: function() {
                alert('AJAX error saving settings');
            }
        });
    });

    // Voice Preview Tool
    function loadVoicesForProvider(providerElement, voiceElement, provider) {
        voiceElement.prop('disabled', true).html('<option>Cargando voces...</option>');

        if (!provider) {
            voiceElement.html('<option value="">Selecciona primero un proveedor</option>').prop('disabled', true);
            return;
        }

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_get_voices',
                provider: provider,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.voices) {
                    let options = '<option value="">Selecciona una voz</option>';
                    response.data.voices.forEach(function(voice) {
                        options += '<option value="' + voice.id + '">' + voice.name + '</option>';
                    });
                    voiceElement.html(options).prop('disabled', false);
                } else {
                    voiceElement.html('<option value="">No hay voces disponibles</option>').prop('disabled', true);
                }
            },
            error: function() {
                voiceElement.html('<option value="">Error cargando voces</option>').prop('disabled', true);
            }
        });
    }

    $('#preview_provider').on('change', function() {
        const provider = $(this).val();
        const voiceSelect = $('#preview_voice');
        const generateBtn = $('#generate_preview');

        generateBtn.prop('disabled', true);
        loadVoicesForProvider($(this), voiceSelect, provider);
    });

    // Force manual trigger for debugging
    $(document).on('click', '#force_load_voices', function() {
        const provider = $('#preview_provider').val();
        if (provider) {
            loadVoicesForProvider($('#preview_provider'), $('#preview_voice'), provider);
        }
    });

    // Trigger on page load if providers are already selected
    $(function() {
        const initialPreviewProvider = $('#preview_provider').val();
        if (initialPreviewProvider) {
            loadVoicesForProvider($('#preview_provider'), $('#preview_voice'), initialPreviewProvider);
        }

        const initialCustomProvider = $('#custom_provider').val();
        if (initialCustomProvider) {
            loadVoicesForCustomProvider($('#custom_provider'), $('#custom_voice'), initialCustomProvider);
        }

        const initialEditorProvider = $('#editor_provider').val();
        if (initialEditorProvider) {
            loadVoicesForEditor($('#editor_provider'), $('#editor_voice'), initialEditorProvider);
        }
    });

    $('#preview_voice').on('change', function() {
        const voice = $(this).val();
        const generateBtn = $('#generate_preview');
        generateBtn.prop('disabled', !voice);
    });

    $('#generate_preview').on('click', function() {
        const provider = $('#preview_provider').val();
        const voice = $('#preview_voice').val();
        const text = $('#preview_text').val();
        const button = $(this);
        const resultDiv = $('#preview_result');

        if (!provider || !voice || !text.trim()) {
            alert('Por favor completa todos los campos');
            return;
        }

        button.prop('disabled', true).text('Generando...');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_preview_voice',
                provider: provider,
                voice: voice,
                text: text,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.audio_url) {
                    $('#preview_audio_source').attr('src', response.data.audio_url);
                    resultDiv.show();
                } else {
                    alert('Error: ' + (response.data.message || 'Error generando preview'));
                }
            },
            error: function() {
                alert('Error de conexión generando preview');
            },
            complete: function() {
                button.prop('disabled', false).text('Generar Vista Previa');
            }
        });
    });

    // Custom Text Generator
    $('#custom_provider').on('change', function() {
        const provider = $(this).val();
        const voiceSelect = $('#custom_voice');

        if (!provider) {
            voiceSelect.html('<option value="">Usar voz predeterminada</option>').prop('disabled', false);
            return;
        }

        loadVoicesForCustomProvider($(this), voiceSelect, provider);
    });

    function loadVoicesForCustomProvider(providerElement, voiceElement, provider) {
        if (!provider) {
            voiceElement.html('<option value="">Usar voz predeterminada</option>').prop('disabled', false);
            return;
        }

        voiceElement.prop('disabled', true).html('<option>Cargando voces...</option>');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_get_voices',
                provider: provider,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.voices) {
                    let options = '<option value="">Usar voz predeterminada</option>';
                    response.data.voices.forEach(function(voice) {
                        options += '<option value="' + voice.id + '">' + voice.name + '</option>';
                    });
                    voiceElement.html(options).prop('disabled', false);
                } else {
                    voiceElement.html('<option value="">No hay voces disponibles</option>').prop('disabled', true);
                }
            },
            error: function() {
                voiceElement.html('<option value="">Error cargando voces</option>').prop('disabled', true);
            }
        });
    }

    $('#custom_text').on('input', function() {
        const text = $(this).val();
        const charCount = text.length;
        const estimatedCost = (charCount * 0.000015).toFixed(4);

        $('#custom_character_count').text(charCount);
        $('#custom_estimated_cost').text('$' + estimatedCost);
    });

    $('#generate_custom').on('click', function() {
        const provider = $('#custom_provider').val();
        const voice = $('#custom_voice').val();
        const text = $('#custom_text').val();
        const button = $(this);

        if (!text.trim()) {
            alert('Por favor ingresa el texto a generar');
            return;
        }

        button.prop('disabled', true).text('Generando...');
        $('#custom_generation_progress').show();
        $('#custom_progress_fill').css('width', '20%');
        $('#custom_progress_text').text('Enviando solicitud...');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_generate_custom',
                provider: provider,
                voice: voice,
                text: text,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                $('#custom_progress_fill').css('width', '100%');
                $('#custom_progress_text').text('Completado');

                if (response.success && response.data.audio_url) {
                    $('#custom_audio_source').attr('src', response.data.audio_url);
                    $('#custom_download_link').attr('href', response.data.audio_url);
                    $('#custom_result').show();
                } else {
                    alert('Error: ' + (response.data.message || 'Error generando audio'));
                }
            },
            error: function() {
                alert('Error de conexión generando audio');
            },
            complete: function() {
                button.prop('disabled', false).text('Generar Audio');
                setTimeout(function() {
                    $('#custom_generation_progress').hide();
                    $('#custom_progress_fill').css('width', '0%');
                }, 2000);
            }
        });
    });

    // Text Editor for TTS
    $('#extract_content').on('click', function() {
        const postId = $('#editor_post_id').val();
        const button = $(this);

        if (!postId) {
            alert('Por favor ingresa un ID de post válido');
            return;
        }

        button.prop('disabled', true).text('Extrayendo...');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_extract_post_content',
                post_id: postId,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.content) {
                    $('#editor_text').val(response.data.content);
                    $('#editor_text_row, #editor_controls_row').show();
                    $('#editor_post_title').text(response.data.title || 'Post #' + postId);
                } else {
                    alert('Error: ' + (response.data.message || 'No se pudo extraer el contenido'));
                }
            },
            error: function() {
                alert('Error de conexión extrayendo contenido');
            },
            complete: function() {
                button.prop('disabled', false).text('Extraer Contenido');
            }
        });
    });

    $('#save_edited_text').on('click', function() {
        const postId = $('#editor_post_id').val();
        const content = $('#editor_text').val();
        const button = $(this);

        if (!postId || !content.trim()) {
            alert('ID de post y contenido son requeridos');
            return;
        }

        button.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_save_edited_text',
                post_id: postId,
                content: content,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Texto guardado exitosamente');
                } else {
                    alert('Error: ' + (response.data.message || 'Error guardando texto'));
                }
            },
            error: function() {
                alert('Error de conexión guardando texto');
            },
            complete: function() {
                button.prop('disabled', false).text('Guardar Texto Editado');
            }
        });
    });

    $('#generate_from_edited').on('click', function() {
        const postId = $('#editor_post_id').val();
        const content = $('#editor_text').val();
        const button = $(this);

        if (!postId || !content.trim()) {
            alert('ID de post y contenido son requeridos');
            return;
        }

        button.prop('disabled', true).text('Generando TTS...');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_generate_from_edited',
                post_id: postId,
                content: content,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Audio TTS generado exitosamente para el post #' + postId);
                } else {
                    alert('Error: ' + (response.data.message || 'Error generando TTS'));
                }
            },
            error: function() {
                alert('Error de conexión generando TTS');
            },
            complete: function() {
                button.prop('disabled', false).text('Generar TTS desde Texto Editado');
            }
        });
    });

    // Editor provider change handler
    $('#editor_provider').on('change', function() {
        const provider = $(this).val();
        const voiceSelect = $('#editor_voice');

        if (!provider) {
            voiceSelect.html('<option value="">Seleccionar Voz</option>').prop('disabled', false);
            return;
        }

        loadVoicesForEditor($(this), voiceSelect, provider);
    });

    function loadVoicesForEditor(providerElement, voiceElement, provider) {
        if (!provider) {
            voiceElement.html('<option value="">Seleccionar Voz</option>').prop('disabled', false);
            return;
        }

        voiceElement.prop('disabled', true).html('<option>Cargando voces...</option>');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_get_voices',
                provider: provider,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.voices) {
                    let options = '<option value="">Seleccionar Voz</option>';
                    response.data.voices.forEach(function(voice) {
                        options += '<option value="' + voice.id + '">' + voice.name + '</option>';
                    });
                    voiceElement.html(options).prop('disabled', false);
                } else {
                    voiceElement.html('<option value="">No hay voces disponibles</option>').prop('disabled', true);
                }
            },
            error: function() {
                voiceElement.html('<option value="">Error cargando voces</option>').prop('disabled', true);
            }
        });
    }

    // Editor text change handler for stats
    $('#editor_text').on('input', function() {
        const text = $(this).val();
        const charCount = text.length;
        const wordCount = text.trim().split(/\s+/).filter(word => word.length > 0).length;
        const estimatedCost = (charCount * 0.000015).toFixed(4);

        $('#editor_character_count').text(charCount);
        $('#editor_word_count').text(wordCount);
        $('#editor_estimated_cost').text('$' + estimatedCost);
    });

    // Test provider connection button
    $('.test-provider-connection').on('click', function() {
        const button = $(this);
        const provider = button.data('provider');
        const resultSpan = $('#test-result-' + provider);

        button.prop('disabled', true).text('Testing...');
        resultSpan.text('Testing connection...');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_tts_validate_provider',
                provider: provider,
                nonce: wpTtsAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: ' + (response.data.valid ? 'green' : 'red') + ';">' + response.data.message + '</span>');
                } else {
                    resultSpan.text('Error: ' + (response.data.message || 'Validation failed.'));
                }
            },
            error: function() {
                resultSpan.text('AJAX error during validation.');
            },
            complete: function() {
                button.prop('disabled', false).text('Test Connection');
            }
        });
    });
});
