<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="Gilles Migliori">

    <!-- Bootstrap CSS -->

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">

    <title>Sitemap Crawler - Ajax Demo</title>
</head>
<body>
    <div class="container">
        <h1 class="text-center my-5">Sitemap Crawler - Ajax Demo</h1>

        <!-- main wrapper -->

        <div id="crawler-wrapper" class="d-none mb-5">

            <!-- progress -->

            <div id="progress" class="d-flex justify-content-between">
                <p>Found <span class="badge-secondary px-2" id="links-counter">0</span> links</p>
                <p class="text-nowrap">Elapsed time: <span class="badge-secondary ml-1 px-2" id="chronotime"></span></p>
            </div>

            <!-- final results -->

            <textarea id="crawler-results" rows="10" class="w-100 small px-3 mb-5"></textarea>

            <!-- final summary report -->

            <div id="final-results-wrapper" class="d-none">
                <p class="lead">The sitemap has been generated in <span class="badge-primary ml-1 px-2" id="chrono-end-time"></span></p>
                <p class="lead">The crawler found <span class="badge-primary mx-1 px-2" id="links-end-count"></span> valid urls</p>
                <p class="lead">The crawler found <span class="badge-primary mx-1 px-2" id="images-end-count"></span> valid images</p>
            </div>

            <!-- submission results report -->

            <div id="submission-results-wrapper" class="d-none"></div>
        </div>

        <button type="button" id="ajax-call" class="btn btn-primary">Generate Sitemap</button>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script type="text/javascript">
        var $chronoTarget,
            startTime = 0,
            start = 0,
            end = 0,
            diff = 0,
            timerID = 0;

        var chrono = function() {
            end = new Date();
            diff = end - start;
            diff = new Date(diff);
            var sec = diff.getSeconds();
            var min = diff.getMinutes();
            var hr = diff.getHours()-1;
            if (min < 10){
                min = "0" + min;
            }
            if (sec < 10){
                sec = "0" + sec;
            }
            $chronoTarget.html(hr + ":" + min + ":" + sec);
            timerID = setTimeout(function() {
                chrono();
            }, 10);
        };

        var chronoStart = function() {
            start = new Date();
            chrono();
        };

        var chronoStop = function() {
            clearTimeout(timerID);
        };

        $(document).ready(function() {
            $chronoTarget = $('#chronotime');
            $('#ajax-call').on('click', function() {
                var $crawlerWrapper           = $('#crawler-wrapper'),
                    $target                   = $('#crawler-results'),
                    $finalResultsWrapper      = $('#final-results-wrapper'),
                    $submissionResultsWrapper = $('#submission-results-wrapper'),
                    $chronoEndTime            = $('#chrono-end-time'),
                    $linksCounter             = $('#links-counter'),
                    $linksEndCount            = $('#links-end-count'),
                    $imagesEndCount            = $('#images-end-count'),
                    linksCount                = 0,
                    imagesCount               = 0;

                $crawlerWrapper.fadeOut().removeClass('d-none').fadeIn('slow');

                var xhr     = new XMLHttpRequest();
                xhr.open('POST', 'standalone-demo.php', true);
                xhr.setRequestHeader('Cache-Control', 'no-cache');
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.send('ajax_enabled=true');

                xhr.onreadystatechange = function() {
                  if (xhr.status == 200) {
                    var response = xhr.response.split(/\r\n|\r|\n/);
                    if (xhr.readyState == XMLHttpRequest.LOADING) {
                        console.log('Loading');
                        linksCount = response.length;
                        $target.text(response);
                        $linksCounter.text(linksCount);
                    }
                    if (xhr.readyState == XMLHttpRequest.DONE) {
                        console.log('Done');
                        $response = xhr.response.split('--split--')
                        $target.text($response[0].split(/\r\n|\r|\n/));

                        imagesCount = $response[1];

                        if (typeof($response[2]) !== undefined) {
                            $submissionResultsWrapper.html($response[2]);
                            $submissionResultsWrapper.fadeOut().removeClass('d-none').fadeIn('slow');
                        }

                        // stop chrono
                        setTimeout(function() {
                            chronoStop();
                        }, 200);

                        // show final results
                        var endTime = $chronoTarget.text().split(':');
                        $chronoEndTime.text(endTime[0] + ' hours ' + endTime[1] + ' minutes ' + endTime[2] + ' seconds');
                        $linksEndCount.text($linksCounter.text());
                        $imagesEndCount.text(imagesCount);
                        $finalResultsWrapper.fadeOut().removeClass('d-none').fadeIn('slow');
                    }
                  }
                }
                // start chrono
                chronoStart();
            });
        });
    </script>
</body>
</html>
