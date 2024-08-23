<?php 
    require 'db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP File Input</title>
</head>
<body>
    <form action="http://192.168.31.95:3572/server.php" method="POST" enctype="multipart/form-data">
        <input type="text" name="name" id="name" placeholder="Your Name">
        <button role="button" type="submit">submit</button>
    </form>

    <script>
        const form = document.querySelector('form');

        form.addEventListener('submit', async function(event){
            event.preventDefault();

            const response = await fetch(form.getAttribute('action'), {
                method: 'POST',
                headers:{
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    name: document.querySelector('#name').value,
                })
            })

            const result = await response.json();
            console.log(result)
        })
    </script>
</body>
</html>