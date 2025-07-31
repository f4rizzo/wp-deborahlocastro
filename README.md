# WordPress Custom Project – Fabrizio

Questo repository contiene **solo le personalizzazioni** del sito WordPress sviluppato in locale con Local, escluse le parti core e i plugin premium.

## 📁 Struttura del repository

wp-content/
├── plugins/
│   └── fabrizio-plugin/           # Plugin custom sviluppato da zero
├── themes/
│   └── fabrizio-theme/            # Tema custom sviluppato da zero
│       ├── acf-json/              # Configurazione ACF salvata in JSON
│       └── elementor-templates/   # Template Elementor esportati

## ⚙️ Requisiti

- WordPress (>= 6.x)
- PHP >= 7.4
- MySQL >= 5.7
- ACF PRO (non incluso)
- Elementor PRO (non incluso)

## 🧰 Plugin richiesti (da installare manualmente)

| Plugin         | Versione minima | Note                      |
|----------------|------------------|---------------------------|
| Advanced Custom Fields PRO | 6.x | Obbligatorio per i campi custom |
| Elementor PRO  | 3.x              | Obbligatorio per il layout |

## 💾 Setup progetto

1. Clona il repository:
   ```bash
   git clone https://github.com/tuo-utente/wordpress-custom-fabrizio.git

2. Installa WordPress (via Local o altro ambiente)
3. Copia le cartelle plugins/fabrizio-plugin e themes/fabrizio-theme nel tuo wp-content/
4. Installa i plugin richiesti (ACF PRO, Elementor PRO)
5. Importa il database di sviluppo (non incluso nel repository)
6. Elementor:
   •	Vai su Template salvati
   •	Reimporta quelli da elementor-templates/ se necessario


💡 Note
•	I campi ACF vengono salvati automaticamente in acf-json/ e caricati dal tema
•	Non vengono salvati: upload, wp-config.php, WordPress core
