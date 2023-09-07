<?php
include'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webcam App</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
   
        <style>
        body {
            background-color: #f8f9fa;
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.2);
        }

        h2 {
            color: #333;
        }

        video {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: block;
        }

        canvas {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: block;
        }

        .btn {
            margin-right: 10px;
        }

        select {
            width: 100%;
        }

        ul {
            list-style: none;
            padding: 0;
        }

        li {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .download-btn {
            background-color: #343a40;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .download-btn:hover {
            background-color: #23272b;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6">
                <h2>Webcam Feed</h2>
                <video id="webcam" autoplay class="img-fluid" playsinline></video>
                <canvas id="filterCanvas" style="display: none;" class="img-fluid"></canvas>
                <br>
                <button class="btn btn-primary" id="snapshotBtn">Take Snapshot</button>
                <button class="btn btn-primary" id="startRecordingBtn">Start Recording</button>
                <button class="btn btn-danger" id="stopRecordingBtn" disabled>Stop Recording</button>
<br><br>
                <select id="filterSelect" class="form-control">
                    <option value="none">No Filter</option>
                    <option value="grayscale">Grayscale</option>
                </select>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-6">
                <h2>Recorded Media</h2>
                <ul id="recordedMedia">
                </ul>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script src="https://cdn.webrtc-experiment.com/RecordRTC.js"></script>

    <script>
        let mediaRecorder;
        let recordedVideoBlob = null;
        let videoStream;

        async function startWebcam() {
            try {
                videoStream = await navigator.mediaDevices.getUserMedia({ video: true });
                const videoElement = document.getElementById('webcam');
                videoElement.srcObject = videoStream;

                document.getElementById('snapshotBtn').addEventListener('click', takeSnapshot);
                document.getElementById('startRecordingBtn').addEventListener('click', startRecording);
                document.getElementById('stopRecordingBtn').addEventListener('click', stopRecording);

            } catch (error) {
                console.error('Error accessing webcam:', error);
            }
        }

        function applyFilter() {
            const filterSelect = document.getElementById('filterSelect');
            const selectedFilter = filterSelect.value;
            const videoElement = document.getElementById('webcam');

            if (selectedFilter === 'grayscale') {
                videoElement.style.filter = 'grayscale(100%)';
            } else {
                videoElement.style.filter = 'none';
            }
        }

        const filterSelect = document.getElementById('filterSelect');
        filterSelect.addEventListener('change', applyFilter);

        async function takeSnapshot() {
            const videoElement = document.getElementById('webcam');
            const snapshotCanvas = document.createElement('canvas');
            snapshotCanvas.width = videoElement.videoWidth;
            snapshotCanvas.height = videoElement.videoHeight;
            const snapshotCtx = snapshotCanvas.getContext('2d');
            snapshotCtx.filter = getSelectedFilter(); // Apply the selected filter
            snapshotCtx.drawImage(videoElement, 0, 0, snapshotCanvas.width, snapshotCanvas.height);

            const snapshotImage = document.createElement('img');
            snapshotImage.src = snapshotCanvas.toDataURL('image/jpeg');
            snapshotImage.classList.add('img-fluid');
            const listItem = document.createElement('li');
            listItem.appendChild(snapshotImage);

            const downloadButton = document.createElement('button');
            downloadButton.innerText = 'Download Image';
            downloadButton.classList.add('download-btn');
            downloadButton.addEventListener('click', () => {
                downloadSnapshot(snapshotCanvas.toDataURL('image/jpeg'), 'snapshot.jpg');
            });

            listItem.appendChild(downloadButton);

            const recordedMediaList = document.getElementById('recordedMedia');
            recordedMediaList.appendChild(listItem);
        }

        function getSelectedFilter() {
            const filterSelect = document.getElementById('filterSelect');
            return filterSelect.value === 'grayscale' ? 'grayscale(100%)' : 'none';
        }

        function startRecording() {
            if (typeof RecordRTC !== 'undefined') {
                mediaRecorder = new RecordRTC(videoStream, {
                    type: 'video',
                    canvas: {
                        width: videoStream.getVideoTracks()[0].getSettings().width,
                        height: videoStream.getVideoTracks()[0].getSettings().height,
                        context: '2d',
                        filter: getSelectedFilter(), 
                    },
                });
                mediaRecorder.startRecording();
                document.getElementById('startRecordingBtn').disabled = true;
                document.getElementById('stopRecordingBtn').disabled = false;
            } else {
                console.error('RecordRTC is not supported in this browser.');
            }
        }

        async function stopRecording() {
            if (mediaRecorder) {
                mediaRecorder.stopRecording(function () {
                    const blob = mediaRecorder.getBlob();
                    recordedVideoBlob = blob;
                    const videoName = `video_${Date.now()}.mp4`;
                    saveVideoAsMP4(blob, videoName);

                    const videoElement = document.createElement('video');
                    videoElement.controls = true;
                    videoElement.src = URL.createObjectURL(blob);
                    videoElement.style.filter = getSelectedFilter(); // Apply the selected filter

                    const downloadButton = document.createElement('button');
                    downloadButton.innerText = 'Download Video';
                    downloadButton.classList.add('download-btn');
                    downloadButton.addEventListener('click', () => {
                        downloadRecordedVideo(blob, videoName);
                    });

                    const listItem = document.createElement('li');
                    listItem.appendChild(videoElement);
                    listItem.appendChild(downloadButton);

                    const recordedMediaList = document.getElementById('recordedMedia');
                    recordedMediaList.appendChild(listItem);
                });
            }

            document.getElementById('startRecordingBtn').disabled = false;
            document.getElementById('stopRecordingBtn').disabled = true;
        }

        async function saveVideoAsMP4(blob, fileName) {
            const formData = new FormData();
            formData.append('webm', blob, fileName);

            try {
                const response = await fetch('save.php', {
                    method: 'POST',
                    body: formData,
                });

                if (response.ok) {
                    console.log('Video saved successfully.');
                } else {
                    console.error('Error saving video:', response.statusText);
                }
            } catch (error) {
                console.error('Error saving video:', error);
            }
        }

        function downloadSnapshot(dataURL, fileName) {
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = dataURL;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function downloadRecordedVideo(blob, fileName) {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
        }

        window.addEventListener('DOMContentLoaded', startWebcam);
    </script>
    
</body>
</html>
