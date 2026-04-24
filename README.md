# Mailer Service

Un semplice ma potente servizio di accodamento e invio email basato su PHP, PHPMailer e un database SQL. Progettato per gestire l'invio asincrono di email tramite API.

## Caratteristiche

- **API REST**: Endpoint POST per accodare le email in modo rapido.
- **Queue Management**: Le email vengono salvate in un database e inviate da un worker separato.
- **Supporto Allegati**: Gestione di allegati (anche in formato Base64).
- **Sicurezza**: Protezione tramite API Key personalizzabile.
- **Asincrono**: L'invio effettivo avviene tramite un processo worker (CLI), evitando rallentamenti nelle risposte web.
- **Logging**: Registrazione dettagliata degli invii e degli errori SMTP.

## Struttura del Progetto

- `public/index.php`: Entry point per le richieste API.
- `worker.php`: Script CLI per elaborare la coda e inviare le email.
- `app/`: Contiene la logica di business (Controller, Modelli, Core).
- `database/`: Migrazioni e seeder per la struttura SQL.
- `storage/logs/`: Log degli errori e attività.

## Installazione

1. Clona il repository.
2. Esegui `composer install`.
3. Crea un file `.env` partendo dalle variabili richieste (vedi sotto).
4. Esegui le migrazioni:
   ```bash
   php migrate.php
   php seed.php
   ```

## Configurazione (.env)

Il sistema richiede le seguenti variabili d'ambiente:

```dotenv
API_KEY=tua_api_key_segreta
DB_HOST=localhost
DB_NAME=nome_database
DB_USER=utente_db
DB_PASS=password_db

SMTP_HOST=smtp.esempio.it
SMTP_USER=email@esempio.it
SMTP_PASS=password_smtp
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_FROM_NAME="Nome Mittente"
```

## Utilizzo API

### Inviare una Email

**Endpoint:** `POST /index.php`  
**Headers:** `X-API-KEY: <tua_api_key_segreta>`

**Esempio Body (JSON):**

```json
{
    "to": "destinatario@esempio.it",
    "subject": "Oggetto della Email",
    "body": "<h1>Ciao!</h1><p>Contenuto dell'email.</p>",
    "attachments": [
        {
            "filename": "documento.pdf",
            "content": "BASE64_ENCODED_CONTENT",
            "mime": "application/pdf"
        }
    ]
}
```

## Automazione (Cron)

Per processare la coda automaticamente, configura un Cron Job che esegua il worker ogni minuto:

```cron
* * * * * php /percorso/progetto/worker.php >> /percorso/progetto/storage/logs/cron.log 2>&1
```

## Licenza

Sviluppato da [Andrea Lagaccia](mailto:info@deltadigital.it).
