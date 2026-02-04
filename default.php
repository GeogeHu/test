<?php
$messages_buffer_file = "messages.json";
$messages_buffer_size = 500;

if (isset($_POST["content"]) && isset($_POST["name"])) {

    $name = trim($_POST["name"]);
    $content = trim($_POST["content"]);

    if ($name === "" || $content === "") exit;
    if (strlen($name) > 50) exit;
    if (strlen($content) > 5000) exit;

    if (!file_exists($messages_buffer_file))
        touch($messages_buffer_file);

    $buffer = fopen($messages_buffer_file, "c+");
    flock($buffer, LOCK_EX);

    $buffer_data = stream_get_contents($buffer);

    $messages = json_decode($buffer_data, true);
    if (!is_array($messages)) $messages = [];

    $next_id = 0;
    if (!empty($messages)) {
        $last = end($messages);
        $next_id = ($last["id"] ?? -1) + 1;
    }

    $messages[] = [
        "id" => $next_id,
        "time" => time(),
        "name" => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        "content" => htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
    ];

    if (count($messages) > $messages_buffer_size)
        $messages = array_slice($messages, -$messages_buffer_size);

    ftruncate($buffer, 0);
    rewind($buffer);
    fwrite($buffer, json_encode($messages, JSON_UNESCAPED_UNICODE));

    flock($buffer, LOCK_UN);
    fclose($buffer);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Msg</title>
<link rel="icon" href="favicon.ico">
<style>
body { font-family: sans-serif; padding:20px; }
#messages { height:500px; overflow:auto; border:1px solid #ccc; list-style:none; padding:5px; }
#messages li { margin:5px 0; }
small { color:gray; font-size:12px; }
input { margin:3px; }
</style>
</head>
<body>

<h1>XCM SEO Chat (Latest 500)</h1>

<ul id="messages">
<li>loading…</li>
<template>
<li class="pending">
<small>…</small>
<span>…</span>
</li>
</template>
</ul>

<form method="post">
<input name="name" placeholder="Your name">
<input name="content" placeholder="Message" style="width:500px">
<button>Send</button>
</form>

<script type="module">

// ===== cookie 工具 =====
function setCookie(name, value, days = 3650) {
    const d = new Date()
    d.setTime(d.getTime() + days*24*60*60*1000)
    document.cookie = `${name}=${encodeURIComponent(value)};expires=${d.toUTCString()};path=/`
}

function getCookie(name) {
    const v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)')
    return v ? decodeURIComponent(v.pop()) : ''
}

// ===== 初始化 =====
const list = document.querySelector("#messages")
list.dataset.lastMessageId = -1

const firstLi = list.querySelector("li")
if (firstLi) firstLi.remove()

const form = document.querySelector("form")
form.name.value = getCookie("chat_name")

// ===== 发送 =====
form.addEventListener("submit", async e => {
    e.preventDefault()
    const f = e.target
    const name = f.name.value.trim()
    const content = f.content.value.trim()
    if (!name || !content) return

    setCookie("chat_name", name)

    await fetch("", {
        method: "POST",
        body: new URLSearchParams({ name, content })
    })

    f.content.value = ""
})

// ===== 轮询 =====
async function poll() {
    const res = await fetch("messages.json?ts=" + Date.now())
    if (!res.ok) return

    const msgs = await res.json()
    const template = list.querySelector("template").content.querySelector("li")

    let lastId = parseInt(list.dataset.lastMessageId)
    let hasNew = false

    for (const m of msgs) {
        if (m.id > lastId) {
            const li = template.cloneNode(true)
            li.classList.remove("pending")

            const d = new Date(m.time * 1000)
            li.querySelector("small").textContent =
                d.toLocaleString() + " " + m.name

            li.querySelector("span").textContent = m.content

            list.append(li)
            lastId = m.id
            hasNew = true
        }
    }

    list.dataset.lastMessageId = lastId

    if (hasNew) {
        const nearBottom =
            list.scrollHeight - list.scrollTop - list.clientHeight < 50

        if (nearBottom)
            list.scrollTop = list.scrollHeight
    }
}

poll()
setInterval(poll, 2000)

</script>

</body>
</html>
