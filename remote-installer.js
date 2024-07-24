jQuery(document).ready(function($) {
    $('#fetch-plugins').on('click', function() {
        $.ajax({
            url: remoteInstaller.ajax_url,
            method: 'POST',
            data: {
                action: 'fetch_files',
                nonce: remoteInstaller.fetch_files_nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#plugins-list').html('<h2>Plugins</h2><ul></ul>');
                    response.data.plugins.forEach(function(plugin) {
                        var pluginName = plugin.split('/').pop(); // Извлекаем имя файла
                        $('#plugins-list ul').append('<li>' + pluginName + ' <button class="install-plugin" data-url="' + plugin + '">Установить</button></li>');
                    });

                    $('#themes-list').html('<h2>Themes</h2><ul></ul>');
                    response.data.themes.forEach(function(theme) {
                        $('#themes-list ul').append('<li>' + theme + ' <button class="install-theme" data-url="' + theme + '">Установить</button></li>');
                    });

                    $('.install-plugin, .install-theme').on('click', function() {
                        var fileUrl = $(this).data('url');
                        var type = $(this).hasClass('install-plugin') ? 'plugin' : 'theme';
                        $.ajax({
                            url: remoteInstaller.ajax_url,
                            method: 'POST',
                            data: {
                                action: 'install_file',
                                file_url: fileUrl,
                                type: type,
                                nonce: remoteInstaller.install_file_nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('File installed successfully.');
                                } else {
                                    alert('Error: ' + response.data);
                                }
                            }
                        });
                    });
                } else {
                    alert('Error fetching files.');
                }
            }
        });
    });
});
