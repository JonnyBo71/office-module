@extends('office::layouts.app')

@section('content')
    <form id="form1" enctype="multipart/form-data">
        <header>
            <a href="">
                <img src ="{{ Module::asset('office:css/images/logo.png') }}" alt="REGAGRO OFFICE" />
            </a>
        </header>
        <div class="main-panel">
            <span class="portal-name">Редактор документов REGAGRO</span>
            <br />
            <br />
            <span class="portal-descr">Начните работу с демонстрационного образца редактора документов REGAGRO, на базе html5. Вы можете загрузить свои собственные документы для тестирования, нажав кнопку "Загрузить файл" и выбрав необходимые файлы на вашем компьютере.</span>
            <br />
            <br />

            <div class="help-block">
                <span>Загрузите ваш файл или создайте новый</span>
                <span>Создайте новый файл</span>
                <br />
                <br />
                <div class="clearFix">
                    <div class="upload-panel clearFix">
                        <a class="file-upload">Загрузите файл
                            <input type="file" id="fileupload" name="files" data-url="/plugins/onlyoffice/webeditor?type=upload" />
                        </a>
                        <br />
                        <label class="save-original">
                            <input type="checkbox" id="checkOriginalFormat" class="checkbox" />Выберите формат файла
                        </label>
                    </div>
                    <div class="create-panel clearFix">
                        <ul class="try-editor-list clearFix">
                            <li>
                                <a class="try-editor document reload-page" target="_blank" href="/plugins/onlyoffice/editor/?fileExt=docx&user=<?php echo $currentUser->id; ?>">Создать документ</a>
                            </li>
                            <li>
                                <a class="try-editor spreadsheet reload-page" target="_blank" href="/plugins/onlyoffice/editor/?fileExt=xlsx&user=<?php echo $currentUser->id; ?>">Создать таблицу</a>
                            </li>
                            <li>
                                <a class="try-editor presentation reload-page" target="_blank" href="/plugins/onlyoffice/editor/?fileExt=pptx&user=<?php echo $currentUser->id; ?>">Создать презентацию</a>
                            </li>
                        </ul>
                        <label class="create-sample">
                            <input type="checkbox" id="createSample" class="checkbox" /> Создать файл используя шаблон
                        </label>
                    </div>
                </div>
            </div>
            <br />
            <br />

            <div class="help-block">
                <span>Ваши документы</span>
                <br />
                <br />

                <div class="stored-list">
                    <div id="UserFiles">
                        <table cellspacing="0" cellpadding="0" width="100%">
                            <thead>
                            <tr class="tableHeader">
                                <td class="tableHeaderCell tableHeaderCellFileName">Имя файла</td>
                                <td colspan="5" class="tableHeaderCell contentCells-shift">Редактировать</td>
                                <td colspan="3" class="tableHeaderCell">Просмотреть</td>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                                $editorUrl = '/plugins/office/editor/';
                            foreach ($storedFiles as &$storeFile) :
                            ?>

                                <tr class="tableRow" title="<?php echo $storeFile->name; ?>">
                                    <td class="contentCells">
                                        <a class="stored-edit <?php echo $storeFile->documentType; ?>" href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>" target="_blank">
                                            <span title="'.$storeFile->name.'"><?php echo $storeFile->name; ?></span>
                                        </a>
                                        <a href="<?php echo $storeFile->fileUri; ?>">
                                            <img class="icon-download" src="{{ Module::asset('office:css/images/download-24.png') }}" alt="Скачать" title="Скачать" />
                                        </a>
                                        <a class="delete-file" data="<?php echo $storeFile->name; ?>">
                                            <img class="icon-delete" src="{{ Module::asset('office:css/images/delete-24.png') }}" alt="Удалить" title="Удалить" />
                                        </a>
                                    </td>

                                    <td class="contentCells contentCells-icon">
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=edit&type=desktop" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/desktop-24.png') }}" alt="Открыть в редакторе на все окно" title="Открыть в редакторе на все окно" />
                                        </a>
                                    </td>
                                    <!--td class="contentCells contentCells-icon">
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=edit&type=mobile" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/mobile-24.png') }}" alt="Открыть в редакторе для мобильного устройства" title="Открыть в редакторе для мобильного устройства" />
                                        </a>
                                    </td-->
                                    <td class="contentCells contentCells-icon">
                                    <?php if ($storeFile->documentType == "text") : ?>
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=review&type=desktop" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/review-24.png') }}" alt="Открыть в редакторе для изменения" title="Открыть в редакторе для изменения" />
                                        </a>
                                    <?php  elseif ($storeFile->documentType == "spreadsheet") : ?>
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=filter&type=desktop" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/filter-24.png') }}" alt="Открыть в редакторе без смены фильтров" title="Открыть в редакторе без смены фильтров" />
                                        </a>
                                    <?php endif; ?>
                                    </td>
                                    <td class="contentCells contentCells-icon">
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=comment&type=desktop" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/comment-24.png') }}" alt="Открыть в редакторе для комментирования" title="Открыть в редакторе для комментирования" />
                                        </a>
                                    </td>
                                    <td class="contentCells contentCells-icon">
                                        <?php if ($storeFile->documentType == "text") : ?>
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=fillForms&type=desktop" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/fill-forms-24.png') }}" alt="Открыть в редакторе для заполнения форм" title="Открыть в редакторе для заполнения форм" />
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="contentCells contentCells-shift contentCells-icon">
                                        <?php if ($storeFile->documentType == "text") : ?>
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=blockcontent&type=desktop" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/block-content-24.png') }}" alt="Открыть в редакторе без изменения элемента управления содержимым" title="Открыть в редакторе без изменения элемента управления содержимым" />
                                        </a>
                                        <?php endif; ?>
                                    </td>

                                    <td class="contentCells contentCells-icon">
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=view&type=desktop" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/desktop-24.png') }}" alt="Открыть для просмотра для полноразмерных экранов" title="Открыть для просмотра для полноразмерных экранов" />
                                        </a>
                                    </td>
                                    <td class="contentCells contentCells-icon">
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=view&type=mobile" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/mobile-24.png') }}" alt="Открыть для просмотра для мобильных устройств" title="Открыть для просмотра для мобильных устройств" />
                                        </a>
                                    </td>
                                    <td class="contentCells contentCells-icon">
                                        <a href="<?php echo $editorUrl.'?fileID='.urlencode($storeFile->name).'&user='.$currentUser->id; ?>&action=embedded&type=embedded" target="_blank">
                                            <img src="{{ Module::asset('office:css/images/embeded-24.png') }}" alt="Открыть во встроенном режиме" title="Открыть во встроенном режиме" />
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach;  ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <br />
            <br />
            <br />
        </div>

        <div id="mainProgress">
            <div id="uploadSteps">
                <span id="step1" class="step">1. Загрузка файла</span>
                <span class="step-descr">Процесс загрузки файла займет некоторое время в зависимости от размера файла, наличия или отсутствия в нем дополнительных элементов (макросов и т. Д.) и скорости соединения.</span>
                <br />
                <span id="step2" class="step">2. Преобразование файлов</span>
                <span class="step-descr">Файл преобразуется в формат Office Open XML для более быстрого просмотра и редактирования документа.</span>
                <br />
                <span id="step3" class="step">3. Загрузка скриптов редактора</span>
                <span class="step-descr">Скрипты для редактора загружаются только один раз и в дальнейшем будут кэшироваться на вашем компьютере. Это может занять некоторое время в зависимости от скорости соединения.</span>
                <input type="hidden" name="hiddenFileName" id="hiddenFileName" />
                <br />
                <br />
                <span class="progress-descr">Обратите внимание, что скорость выполнения всех операций сильно зависит от расположения сервера и клиента. В случае, если они отличаются или расположены в разных странах/континентах, может быть недостаточная скорость и большее время ожидания. Наилучшие результаты достигаются, когда серверный и клиентский компьютеры расположены в одном и том же месте (городе).</span>
                <br />
                <br />
                <div class="error-message">
                    <span></span>
                    <br />
                    Пожалуйста, выберите другой файл и повторите попытку.
                </div>
            </div>
            <iframe id="embeddedView" src="" height="345px" width="600px" frameborder="0" scrolling="no" allowtransparency></iframe>
            <br />
            <div id="beginEmbedded" class="button disable">Встроенный вид</div>
            <div id="beginView" class="button disable">Просмотр</div>

            <?php //if (($config['MODE']) != "view") { ?>
            <div id="beginEdit" class="button disable">Редактирование</div>
            <?php //} ?>
            <div id="cancelEdit" class="button gray">Отмена</div>
        </div>

        <span id="loadScripts" data-docs="<?php echo $config['DOC_SERV_PRELOADER_URL'] ?>"></span>
        <footer>&copy; REGAGRO <?php echo date("Y") ?>. All rights reserved.</footer>

    </form>

    <script type="text/javascript">
        var ConverExtList = '<?php echo implode(",", $config["DOC_SERV_CONVERT"]) ?>';
        var EditedExtList = '<?php echo implode(",", $config["DOC_SERV_EDITED"]) ?>';
    </script>


@endsection
