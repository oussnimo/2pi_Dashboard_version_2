# 2PI Dashboard - AI-Powered Interactive Math Quiz Platform

A modern web application for teachers to create, manage, and export interactive math quizzes with AI-powered question generation. Supports PDF/link content integration and SCORM 1.2 package export for LMS integration.

## 🎯 Key Features

✅ **AI Question Generator** - Generate quiz questions using Groq API with context from PDFs and external links  
✅ **PDF & Link Processing** - Upload PDFs and add reference links for contextual question generation  
✅ **Interactive Quiz Editor** - Create multi-level quizzes with Box (short answer) and Balloon (multiple choice) question types  
✅ **SCORM 1.2 Export** - Export quizzes as packages compatible with Moodle, Canvas, Blackboard, and other LMS platforms  
✅ **User Authentication** - Secure authentication with Laravel Sanctum  
✅ **Real-time Progress Tracking** - Monitor student performance and completion status  
✅ **Responsive Design** - Mobile-friendly interface with Tailwind CSS  
✅ **Multi-language Support** - Internationalization ready  
✅ **Dark/Light Theme** - Theme customization support

## 📋 Project Structure

```
2pi-Dashboard-AI-integration-main/
├── BackEnd/                          # Laravel API Backend
│   ├── app/Http/Controllers/
│   │   ├── AIQuestionController.php  # AI question generation (Groq)
│   │   ├── AttachmentController.php  # PDF parsing & link validation
│   │   ├── ExportController.php      # SCORM package export
│   │   └── ...
│   ├── config/                       # Configuration files
│   ├── database/                     # Migrations & seeders
│   ├── routes/api.php                # API endpoints
│   ├── .env.example                  # Environment variables template
│   └── composer.json
│
├── FrontEnd/                         # React + Vite Frontend
│   ├── src/
│   │   ├── components/
│   │   │   ├── AIQuestionGenerator.jsx  # AI generation interface
│   │   │   ├── ExportQuizDialog.jsx     # SCORM export UI
│   │   │   └── ...
│   │   ├── pages/
│   │   ├── services/
│   │   └── App.jsx
│   ├── .env.example
│   └── package.json
│
└── README.md
```

## 🛠️ Technology Stack

### Frontend

- **React** 18.3 + **Vite** 6.0 - Modern UI framework & bundler
- **React Router** v6.30 - Client-side routing
- **Axios** v1.7 - HTTP client for API calls
- **TailwindCSS** v3.4 - Utility-first CSS framework
- **Framer Motion** v10.18 - Animation library
- **React Hot Toast** v2.4 - Toast notifications
- **Zod** v3.22 - Schema validation

### Backend

- **Laravel** 8+ - PHP web framework
- **Laravel Sanctum** - API token authentication
- **smalot/pdfparser** v2.12.3 - PDF text extraction
- **Groq API** - LLaMA-3.1 & Mixtral LLM models
- **ZipArchive** (PHP native) - SCORM package creation
- **MySQL/PostgreSQL** - Database

## 🚀 Installation

### Prerequisites

- **Node.js** 16+ and npm
- **PHP** 8.0+
- **Composer**
- **MySQL** 5.7+ or **PostgreSQL** 10+
- **Groq API Key** (free at https://console.groq.com)

### Backend Setup

1. Navigate to backend:

```bash
cd BackEnd
```

2. Install dependencies:

```bash
composer install
```

3. Create `.env` file:

```bash
cp .env.example .env
```

4. Configure `.env`:

```env
APP_NAME="2PI Dashboard"
APP_ENV=local
APP_KEY=
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=2pi_dashboard
DB_USERNAME=root
DB_PASSWORD=

GROQ_API_KEY=your_groq_api_key_here
GROQ_MODEL=mixtral-8x7b-32768

SANCTUM_STATEFUL_DOMAINS=localhost:5173

MAIL_FROM_ADDRESS=support@2pidashboard.com
MAIL_FROM_NAME="2PI Dashboard"
```

5. Generate application key:

```bash
php artisan key:generate
```

6. Run migrations:

```bash
php artisan migrate
```

7. Create storage directories:

```bash
mkdir -p storage/app/temp storage/app/pdfs
chmod 755 storage/app/temp storage/app/pdfs
```

8. Start server:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

### Frontend Setup

1. Navigate to frontend:

```bash
cd FrontEnd
```

2. Install dependencies:

```bash
npm install
```

3. Create `.env`:

```bash
cp .env.example .env
```

4. Configure `.env`:

```env
VITE_API_URL=http://localhost:8000/api
VITE_APP_NAME=2PI Dashboard
```

5. Start dev server:

```bash
npm run dev
```

Access at **http://localhost:5173**

## 📚 API Endpoints

### Authentication

```
POST   /api/register              # Register new user
POST   /api/login                 # User login
POST   /api/logout                # User logout
GET    /api/user                  # Get current user (requires auth)
PUT    /api/profile               # Update profile (requires auth)
```

### Quiz Management

```
POST   /api/game                  # Create quiz
GET    /api/select                # Get all quizzes
GET    /api/lastGames             # Get recent quizzes
GET    /api/getGameById           # Get quiz by ID
DELETE /api/delete                # Delete quiz
```

### AI Question Generation

```
POST   /api/generate-questions    # Generate with AI
  Body: {
    course, topic, gameNumber, numLevels,
    level_types, ai_prompt,
    pdf_text (optional), links (optional)
  }
```

### Attachment Processing

```
POST   /api/attachments/process   # Upload PDF & links
  FormData: { pdf (file), links[] (array) }
  Returns: { attachment_id, pdf_text, links }

POST   /api/attachments/retrieve  # Retrieve attachment
  Body: { attachment_id }
```

### SCORM Export

```
POST   /api/export-quiz-zip       # Export as SCORM
  Body: { course, topic, gameNumber, numLevels, levels }
  Returns: scorm_quiz_YYYY-MM-DD_HH-MM-SS.zip
```

## 🔄 Workflow: AI Quiz Generation with PDF/Links

### Step 1: Process Attachments

```
Upload PDF + Links
       ↓
POST /api/attachments/process
       ↓
Backend: Parse PDF, validate URLs
       ↓
Response: { attachment_id, pdf_text, links }
```

### Step 2: Generate with Context

```
PDF content + Links ready
       ↓
POST /api/generate-questions
  { ai_prompt, pdf_text, links }
       ↓
Backend: Enrich prompt with PDF & links
       ↓
Groq API: Generate contextual questions
       ↓
Response: Quiz with levels
```

### Step 3: Export as SCORM

```
User clicks Export
       ↓
POST /api/export-quiz-zip
       ↓
Backend: Create imsmanifest.xml, html, js, css
       ↓
Create ZIP archive
       ↓
Download: scorm_quiz_YYYY-MM-DD_HH-MM-SS.zip
```

## 📄 SCORM Package Structure

```
scorm_quiz_2026-02-27_14-35-22.zip
├── imsmanifest.xml          # SCORM manifest (required)
├── index.html               # Quiz entry point
├── css/style.css           # Styling
├── js/app.js               # Logic & SCORM API
├── content/questions.json   # Quiz data
└── README.txt              # Documentation
```

**Compatible with:** Moodle, Canvas, Blackboard, D2L, Desire2Learn

## 🔐 Environment Variables

### Backend Required

| Variable        | Description         | Example              |
| --------------- | ------------------- | -------------------- |
| `GROQ_API_KEY`  | Groq API key (free) | `gsk_...`            |
| `GROQ_MODEL`    | LLM model           | `mixtral-8x7b-32768` |
| `DB_CONNECTION` | Database type       | `mysql`              |
| `DB_DATABASE`   | Database name       | `2pi_dashboard`      |
| `DB_USERNAME`   | DB user             | `root`               |
| `DB_PASSWORD`   | DB password         |                      |

### Frontend Required

| Variable       | Description     | Example                     |
| -------------- | --------------- | --------------------------- |
| `VITE_API_URL` | Backend API URL | `http://localhost:8000/api` |

## 🧪 Testing

### Test AI Generation

1. Create test PDF with math content
2. Use AIQuestionGenerator:
   - Upload PDF
   - Add reference links
   - Click "Generate"
3. Verify questions reference PDF content

### Test SCORM Export

1. Generate quiz with AI
2. Click "Export SCORM"
3. Extract ZIP and verify structure:
   ```bash
   unzip scorm_quiz_*.zip
   ls -la
   ```
4. Import into Moodle/Canvas

## 📖 Usage Guide

### Creating AI-Powered Quiz

1. **Go to Generator** → Click "Create Quiz"
2. **Upload PDF** (optional) → Attach document
3. **Add Links** (optional) → Include URLs
4. **Enter Prompt** → Describe questions:
   - "Create 3 levels of probability questions"
   - "Focus on word problems"
5. **Configure** → Select 1-4 levels
6. **Generate** → AI creates questions
7. **Review** → Edit if needed
8. **Save** → Store quiz

### Export for LMS

1. **Select Quiz**
2. **Click Export** → "Export as SCORM"
3. **Download** → `scorm_quiz_*.zip`
4. **Import into LMS** → Upload to Moodle/Canvas
5. **Assign** → Make available to students

## 🐛 Troubleshooting

### PDF Not Processing

- Verify PDF is text-based (not image scan)
- File size < 5MB
- Check `storage/app/pdfs/` is writable

### Generic Questions

- Use specific AI prompts
- Upload relevant PDF content
- Include reference links
- Specify difficulty level

### SCORM Export Fails

- Verify `storage/app/temp/` exists
- Check: `tail -f storage/logs/laravel.log`
- Ensure disk space available

### Groq API Errors

- Verify `GROQ_API_KEY` is correct
- Check API quota at https://console.groq.com
- Review rate limits

## 📝 Contributing

1. Fork repository
2. Create branch: `git checkout -b feature/my-feature`
3. Commit: `git commit -m 'Add feature'`
4. Push: `git push origin feature/my-feature`
5. Pull Request

## 📄 License

MIT License - see [LICENSE](LICENSE) file

## 👥 Authors

**Development Team** - 2PI Project

## 🔗 Resources

- [Groq API](https://console.groq.com/docs)
- [Laravel](https://laravel.com/docs)
- [React](https://react.dev)
- [SCORM 1.2](https://www.adlnet.gov/scorm)
