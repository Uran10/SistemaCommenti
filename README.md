# Commenti PHP + MySQL per Jekyll

Il blog resta statico: JavaScript legge e invia commenti a un'API PHP, anche su un dominio diverso. Richiede PHP 8.1+ con `pdo_mysql` e `mbstring`, MySQL 8+ e HTTPS in produzione. Nessuna dipendenza Composer. GitHub Pages può ospitare il blog, ma non eseguire l'API PHP.

## Installazione

1. Crea un database MySQL e importa `database.sql`.
2. Copia `config.example.php` in `config.php`, imposta le credenziali e l'origine esatta del blog, per esempio `https://miosito.it`. Usa un account MySQL dedicato con permessi SELECT, INSERT, UPDATE, DELETE sulle due tabelle.
3. Configura la document root del server PHP sulla sola cartella `public/`. Mantieni `config.php` fuori dalla document root: contiene credenziali. L'endpoint sarà, per esempio, `https://commenti.miosito.it/comments.php`.
4. Copia `jekyll/_includes/comments.html` e `jekyll/assets/comments.js` nelle cartelle corrispondenti del blog.
5. Aggiungi a `_config.yml`:

   ```yaml
   comments_api: "https://commenti.miosito.it/comments.php"
   ```

6. Aggiungi `{% include comments.html %}` nel layout degli articoli, dopo il contenuto, e ricostruisci Jekyll. Se usi una Content Security Policy, autorizza il dominio API in `connect-src`.

Il percorso pubblico dell'articolo identifica la discussione, incluso l'eventuale `baseurl`. Mantieni stabili i permalink; se li cambi, aggiorna `post_path` nel database. Il widget mostra gli ultimi 100 commenti approvati, dal più recente. Non richiede cookie né raccoglie email.

## Moderazione

La moderazione è attiva per impostazione predefinita. Gestisci i commenti con il tuo client MySQL o phpMyAdmin:

```sql
SELECT id, post_path, author, body, created_at
FROM comments WHERE status = 'pending' ORDER BY id;

UPDATE comments SET status = 'approved' WHERE id = 123;
UPDATE comments SET status = 'rejected' WHERE id = 124;
```

Non è incluso un pannello amministrativo pubblico. Per pubblicazione immediata imposta `moderation` a `false` in `config.php`.

## Protezioni e manutenzione

Query parametrizzate, limiti di lunghezza e dimensione, testo visualizzato con `textContent` (nessun HTML nei commenti), campo esca antispam e un invio al minuto per IP. Gli invii richiedono JSON e l'origine autorizzata; il browser effettua il preflight CORS quando necessario. Queste protezioni non fermano bot che chiamano direttamente l'API: per siti esposti a spam aggiungi protezioni al reverse proxy o un CAPTCHA.

Il rate limit usa `REMOTE_ADDR`: dietro proxy condivisi gli utenti potrebbero condividere il limite. Configura il server con proxy fidati prima di usare l'IP inoltrato; non fidarti direttamente di `X-Forwarded-For`. Gli IP sono memorizzati come hash HMAC, da trattare comunque come dati pseudonimizzati. Prevedi backup, una policy privacy e una procedura per cancellare i commenti su richiesta.

Esegui periodicamente per eliminare le voci scadute del rate limit:

```sql
DELETE FROM comment_rate_limits WHERE last_submission < UNIX_TIMESTAMP() - 86400;
```

## Verifica locale

Con MySQL configurato, avvia l'API dalla radice del progetto:

```sh
php -S localhost:8080 -t public
```

Imposta `blog_origin` sull'origine del Jekyll locale, per esempio `http://localhost:4000`, e `comments_api` su `http://localhost:8080/comments.php`. Verifica invio, stato pending, approvazione SQL, visualizzazione dopo ricarica, blocco di un secondo invio entro un minuto e visualizzazione letterale di testo come `<script>alert(1)</script>`.
