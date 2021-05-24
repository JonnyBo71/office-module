@extends('office::layouts.app')

@section('content')

    <script type="text/javascript" src="<?php echo $config["DOC_SERV_SITE_URL"].$config["DOC_SERV_API_URL"] ?>"></script>

    <script type="text/javascript">

        var docEditor;
        var fileName = "<?php echo $fileInfo['filename'] ?>";
        var fileType = "<?php echo $fileInfo['filetype'] ?>";

        var innerAlert = function (message) {
            if (console && console.log)
                console.log(message);
        };

        var onAppReady = function () {
            innerAlert("Редактор документов готов");
        };

        var onDocumentStateChange = function (event) {
            var title = document.title.replace(/\*$/g, "");
            document.title = title + (event.data ? "*" : "");
        };

        var onRequestEditRights = function () {
            location.href = location.href.replace(RegExp("action=view\&?", "i"), "");
        };

        var onError = function (event) {
            if (event)
                innerAlert(event.data);
        };

        var onOutdatedVersion = function (event) {
            location.reload(true);
        };

        var replaceActionLink = function(href, linkParam) {
            var link;
            var actionIndex = href.indexOf("&actionLink=");
            if (actionIndex != -1) {
                var endIndex = href.indexOf("&", actionIndex + "&actionLink=".length);
                if (endIndex != -1) {
                    link = href.substring(0, actionIndex) + href.substring(endIndex) + "&actionLink=" + encodeURIComponent(linkParam);
                } else {
                    link = href.substring(0, actionIndex) + "&actionLink=" + encodeURIComponent(linkParam);
                }
            } else {
                link = href + "&actionLink=" + encodeURIComponent(linkParam);
            }
            return link;
        }

        var onMakeActionLink = function (event) {
            var actionData = event.data;
            var linkParam = JSON.stringify(actionData);
            docEditor.setActionLink(replaceActionLink(location.href, linkParam));
        };

        var onRequestRename = function(event) {
            var title = event.data;

        };

        var onMetaChange = function (event) {
            var favorite = !!event.data.favorite;
            var title = document.title.replace(/^\☆/g, "");
            document.title = (favorite ? "☆" : "") + title;
            docEditor.setFavorite(favorite);
        };

        var onRequestInsertImage = function(event) {
            docEditor.insertImage({
                "c": event.data.c,
                <?php echo mb_strimwidth(json_encode($dataInsertImage), 1, strlen(json_encode($dataInsertImage)) - 2)?>
            })
        };

        var onRequestCompareFile = function() {
            docEditor.setRevisedFile(<?php echo json_encode($dataCompareFile)?>);
        };

        var onRequestMailMergeRecipients = function (event) {
            docEditor.setMailMergeRecipients(<?php echo json_encode($dataMailMergeRecipients) ?>);
        };

        var сonnectEditor = function () {

          <?php
          if (!file_exists($fileInfo['filePath'])) {
            echo "alert('File not found'); return;";
          }
          ?>

            var config = <?php echo json_encode($option) ?>;

            config.width = "100%";
            config.height = "100%";

            config.events = {
                'onAppReady': onAppReady,
                'onDocumentStateChange': onDocumentStateChange,
                'onRequestEditRights': onRequestEditRights,
                'onError': onError,
                'onOutdatedVersion': onOutdatedVersion,
                'onMakeActionLink': onMakeActionLink,
                "onRequestRename": onRequestRename,
                'onMetaChange': onMetaChange,
                'onRequestInsertImage': onRequestInsertImage,
                'onRequestCompareFile': onRequestCompareFile,
                'onRequestMailMergeRecipients': onRequestMailMergeRecipients,
            };

            <?php if ($out && ($out[0] != null && $out[1] != null)): ?>

            config.events['onRequestHistory'] = function () {
                docEditor.refreshHistory(<?php echo json_encode($out[0]) ?>);
            };
            config.events['onRequestHistoryData'] = function (event) {
                var ver = event.data;
                var histData = <?php echo json_encode($out[1]) ?>;
                docEditor.setHistoryData(histData[ver - 1]);
            };
            config.events['onRequestHistoryClose'] = function () {
                document.location.reload();
            };

            <?php endif; ?>

            docEditor = new DocsAPI.DocEditor("iframeEditor", config);
        };

        if (window.addEventListener) {
            window.addEventListener("load", сonnectEditor);
        } else if (window.attachEvent) {
            window.attachEvent("load", сonnectEditor);
        }

    </script>

    <form id="form1">
        <div id="iframeEditor">
        </div>
    </form>

@endsection
