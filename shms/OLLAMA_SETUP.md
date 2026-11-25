# Offline AI setup: Windows + XAMPP + Ollama

SHMS now defaults to local Ollama with `qwen3:4b`. No API key is required.
Ollama and PHP/Apache must run on the SAME computer. A resident's browser does
not need Ollama. This ZIP contains the integration, not the model weights.

## 1. Update the app

Back up your current `C:\xampp\htdocs\shms` folder and database. Extract the
ZIP's `shms` folder there, retaining your database connection settings.
If you already imported the notices/reports upgrade, this AI update needs NO
additional SQL import. Otherwise follow `START_HERE.md` once. Do not import the
fresh-install database over existing data.

If an old `config/ai.local.php` contains online provider settings, keep a backup
outside the web folder and replace its contents with `config/ai.example.php`.
With no `ai.local.php` and no SHMS_AI_* environment overrides, the new defaults
already work. No config copy is necessary for a fresh installation.

## 2. Install and download

Install Ollama for Windows: https://ollama.com/download/windows
Use Windows 10 22H2 or newer / Windows 11. Open a NEW Command Prompt after install:

```bat
ollama pull qwen3:4b
ollama run qwen3:4b
```

The model download is about 2.5 GB; internet is needed for installation/download.
Type a short message to check the model, then `/bye` to leave the chat. Keep the
Ollama application running in the background. If it is not running, open it from
the Start menu. `ollama serve` is an alternative; an address-already-in-use error
usually means it is already running, so do not start a second server.

For strictly local operation, run this once, then quit and restart Ollama:

```bat
setx OLLAMA_NO_CLOUD 1
```

The SHMS default calls only the literal loopback address, bypasses proxies and
rejects redirects and cloud-tagged models. It does not switch to a remote API on
failure. Keep `provider` set to `ollama` for offline use.

## 3. Start XAMPP and check

Start Apache and MySQL. Open `http://localhost/shms/`, log in as Admin, and go
to **Notice Board**. Click **Check Ollama Connection**. It should confirm Ollama
is reachable and `qwen3:4b` is downloaded. This check lists models; generating a
notice tests actual inference.

Enter `Water supply raat 9 baje aaye gi` and click **Generate Professional Notice**.
Allow up to 3 minutes on the default settings, especially for the first request.
The page should say **Offline AI draft is ready**. Review the title and content,
choose Draft or Published, then click **Save Notice**.

Flow: short information -> PHP -> local Ollama -> Qwen -> editable draft ->
admin review/save -> MySQL -> residents see published notices.
Generation and the connection check do not save or publish anything.

After the model is downloaded, this AI path can run with internet disconnected.
The app still references CDN assets for parts of its styling/scripts; this update
does not bundle those assets or promise the entire interface works offline.

## Configuration and troubleshooting

Optional `config/ai.local.php` (copy the example file):

```php
<?php
return [
    'provider' => 'ollama',
    'endpoint' => 'http://127.0.0.1:11434/api/chat',
    'api_key' => '',
    'model' => 'qwen3:4b',
    'timeout' => 180,
];
```

| Result | What to do |
| --- | --- |
| Cannot reach local Ollama | Start Ollama on the XAMPP PC; confirm its local port is 11434. |
| Model not downloaded | Run `ollama pull qwen3:4b` (or the exact configured model name). |
| PHP cURL disabled | In XAMPP Control Panel, Apache > Config > PHP (php.ini), enable `extension=curl`; restart Apache. |
| Timeout or not enough memory | Close heavy apps, warm up the model using `ollama run`, or use the smaller model below. |
| Invalid/incomplete output | One automatic retry runs within the same timeout; if it fails, the reason appears beside Generate. Previous editor content remains unchanged unless a supported translated template is available. |
| Clarification needed | Replace bare ranges such as `3 tak` with a complete date, explicit time or duration. |
| Template draft | This is a fallback, not an AI success. The page displays the reason. |
| Check succeeds, Generate fails | Model download is present but inference failed; check RAM, logs and timeout. |

For an 8 GB machine, actual speed depends on CPU/GPU and free RAM. The 2.5 GB
download size is not total runtime memory. The default uses a 2048-token context,
short output and thinking disabled to limit memory/work. Good factual accuracy
is not guaranteed; review dates, times and wording before publication.

If 4b is too slow or does not fit, download the smaller model:

```bat
ollama pull qwen3:1.7b
```

Then change `model` to `qwen3:1.7b` in `config/ai.local.php`. It may handle complex
wording less reliably. You can raise `timeout` to 300 seconds; values are clamped
to 15–300. Apache/proxy request limits may also need adjustment if a request is
cut off before the configured timeout.

## Optional PHP checks

From Command Prompt in `C:\xampp\htdocs\shms`:

```bat
C:\xampp\php\php.exe tests\unit.php
C:\xampp\php\php.exe tests\ollama.php
C:\xampp\php\php.exe tests\ollama_smoke.php
```

The first two use no model/network/database. The third calls your actual local
Ollama service and prints a sample draft. It does not save a notice. Also test the
Notice Board itself because Apache may use different PHP settings from the CLI.

Official references: https://docs.ollama.com/api/chat
and https://docs.ollama.com/faq
