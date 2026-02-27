import { useState } from "react";
import { motion } from "framer-motion";
import {
  Sparkles,
  X,
  Loader,
  Package,
  Layers,
  Upload,
  Link as LinkIcon,
  Trash2,
} from "lucide-react";
import { useLanguage } from "../hooks/useLanguage";
import toast from "react-hot-toast";
import axios from "axios";

/**
 * AIQuestionGenerator Component - REDESIGNED
 *
 * Generates questions for ALL levels at once based on selected game types and ONE prompt
 * Props:
 *   - isOpen: boolean - Control visibility
 *   - onClose: function - Close the panel
 *   - numLevels: number - Total levels in quiz
 *   - onQuestionsGenerated: function - Callback when all questions are generated
 */

function AIQuestionGenerator({
  isOpen,
  onClose,
  numLevels,
  quizData,
  onQuestionsGenerated,
}) {
  const { t } = useLanguage();

  // Form state: game types for each level + shared prompt
  const [levelGameTypes, setLevelGameTypes] = useState(
    Array(numLevels)
      .fill()
      .reduce((acc, _, i) => {
        acc[i + 1] = "box"; // Default to "box"
        return acc;
      }, {}),
  );

  const [prompt, setPrompt] = useState("");
  const [isGenerating, setIsGenerating] = useState(false);

  // ✅ AJOUTE CES 4 LIGNES
  const [uploadedPDF, setUploadedPDF] = useState(null);
  const [pdfText, setPdfText] = useState("");
  const [links, setLinks] = useState([]);
  const [newLink, setNewLink] = useState("");

  /**
   * Handle game type change for a specific level
   */
  const handleGameTypeChange = (levelNumber, gameType) => {
    setLevelGameTypes((prev) => ({
      ...prev,
      [levelNumber]: gameType,
    }));
  };

  /**
   * Reset form to initial state
   */
  const resetForm = () => {
    setPrompt("");
    setLevelGameTypes(
      Array(numLevels)
        .fill()
        .reduce((acc, _, i) => {
          acc[i + 1] = "box";
          return acc;
        }, {}),
    );
    // ✅ AJOUTE CES 4 LIGNES
    setUploadedPDF(null);
    setPdfText("");
    setLinks([]);
    setNewLink("");
  };

  // ✅ AJOUTE TOUTES CES FONCTIONS ICI (après resetForm)
  const handlePDFUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (file.type !== "application/pdf") {
      toast.error("Veuillez uploader un fichier PDF");
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error("Fichier trop grand (max 5MB)");
      return;
    }

    setUploadedPDF(file);
    setPdfText(`[PDF: ${file.name} - Document de référence]`);
    toast.success("PDF ajouté");
  };

  const handleRemovePDF = () => {
    setUploadedPDF(null);
    setPdfText("");
  };

  const handleAddLink = () => {
    if (!newLink.trim()) return;

    try {
      new URL(newLink);
      setLinks([...links, newLink]);
      setNewLink("");
      toast.success("Lien ajouté");
    } catch {
      toast.error("URL invalide");
    }
  };

  const handleRemoveLink = (index) => {
    setLinks(links.filter((_, i) => i !== index));
  };

  /**
   * Add link directly (for prompt() dialog usage)
   */
  const addLinkDirectly = (url) => {
    if (!url.trim()) return;

    try {
      new URL(url);
      setLinks((prevLinks) => [...prevLinks, url]);
      toast.success("Lien ajouté");
    } catch {
      toast.error("URL invalide");
    }
  };

  /**
   * Handle close with form reset
   */
  const handleClose = () => {
    resetForm();
    onClose();
  };

  /**
   * Generate questions for ALL levels at once using Bulk API
   * STEP 1: If PDF/links present, upload to /attachments/process first
   * STEP 2: Call /generate-questions with attachment data
   */
  const handleGenerateAllQuestions = async () => {
    // Validation
    if (!prompt.trim()) {
      toast.error(t("enter_prompt") || "Please enter a prompt");
      return;
    }

    if (!quizData?.course || !quizData?.topic || !quizData?.gameNumber) {
      toast.error(
        "Please fill Course, Topic, and Game Number on the form first.",
      );
      return;
    }

    // Check if all levels have game types selected
    const allLevelsConfigured = Array.from(
      { length: numLevels },
      (_, i) => i + 1,
    ).every((levelNum) => levelGameTypes[levelNum]);

    if (!allLevelsConfigured) {
      toast.error("Please select a game type for all levels");
      return;
    }

    setIsGenerating(true);
    console.log("🚀 [AIQuestionGenerator] Starting generation process...");

    try {
      const apiUrl =
        import.meta.env.VITE_API_URL || "http://localhost:8000/api/";
      const token =
        localStorage.getItem("token") || sessionStorage.getItem("token");

      if (!token) {
        toast.error("Authentication required. Please log in.");
        setIsGenerating(false);
        return;
      }

      // Convert levelGameTypes object to a simple array in order
      const levelTypesArray = [];
      for (let i = 1; i <= numLevels; i++) {
        levelTypesArray.push(levelGameTypes[i]);
      }

      // ===== STEP 1: Upload PDF + Links to /attachments/process (if needed) =====
      let attachmentData = {
        attachment_id: null,
        pdf_text: "",
        links: links,
      };

      if (uploadedPDF || links.length > 0) {
        console.log(
          "📎 [AIQuestionGenerator] Processing attachments (PDF + links)...",
        );
        toast.loading("Processing attachments...");

        const formData = new FormData();
        if (uploadedPDF) {
          formData.append("pdf", uploadedPDF);
        }
        links.forEach((link) => {
          formData.append("links[]", link);
        });

        try {
          const attachmentResponse = await axios.post(
            `${apiUrl}attachments/process`,
            formData,
            {
              headers: {
                Authorization: `Bearer ${token}`,
                "Content-Type": "multipart/form-data",
              },
            },
          );

          if (attachmentResponse.data.success) {
            attachmentData = attachmentResponse.data.data;
            console.log(
              "✅ [AIQuestionGenerator] Attachments processed:",
              attachmentData,
            );
            toast.dismiss();
          } else {
            console.warn(
              "⚠️ Attachment processing issues:",
              attachmentResponse.data.errors,
            );
            toast.dismiss();
          }
        } catch (attachmentError) {
          const errMsg =
            attachmentError.response?.data?.message ||
            "Failed to process attachments";
          console.error("❌ Attachment upload error:", errMsg);
          toast.error(`Attachment error: ${errMsg}`);
          // Continue anyway - we still have the links array
          toast.dismiss();
        }
      }

      // ===== STEP 2: Prepare payload for /generate-questions =====
      const payload = {
        course: quizData.course,
        topic: quizData.topic,
        gameNumber: parseInt(quizData.gameNumber, 10),
        numLevels: numLevels,
        level_types: levelTypesArray,
        ai_prompt: prompt,
        attachment_id: attachmentData.attachment_id,
        pdf_text: attachmentData.pdf_text,
        links: attachmentData.links,
      };

      console.log("📦 Sending Generate Questions Payload:", payload);
      toast.loading("Generating questions with AI...");

      const response = await axios.post(
        `${apiUrl}generate-questions`,
        payload,
        {
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
        },
      );

      if (response.status === 200 && response.data.success) {
        console.log(
          "🎉 [AIQuestionGenerator] Bulk generation successful!",
          response.data.data,
        );
        toast.dismiss();
        toast.success(
          t("questions_generated") ||
            `All ${numLevels} levels generated successfully!`,
        );

        // Pass the fully structured JSON data up to the parent
        onQuestionsGenerated(response.data.data);
        handleClose();
      } else {
        toast.dismiss();
        console.error("❌ [AIQuestionGenerator] API Error:", response.data);
        toast.error(
          response.data.message ||
            "Failed to generate questions. Please try again.",
        );
      }
    } catch (error) {
      toast.dismiss();
      const errMsg =
        error.response?.data?.message ||
        error.message ||
        "An unexpected error occurred";
      console.error(
        "❌ [AIQuestionGenerator] Network or Server Error:",
        errMsg,
      );
      toast.error(`Error: ${errMsg}`);
    } finally {
      setIsGenerating(false);
    }
  };

  return (
    <motion.div
      className="glass-card p-6 h-full flex flex-col"
      initial={{ scale: 0.95, opacity: 0 }}
      animate={{ scale: 1, opacity: 1 }}
      exit={{ scale: 0.95, opacity: 0 }}
      transition={{ duration: 0.3 }}
    >
      {/* ===== HEADER ===== */}
      <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
        <div className="flex items-center gap-2">
          <Sparkles className="text-purple-main" size={20} />
          <h3 className="font-bold gradient-text">
            {t("ai_question_generator")}
          </h3>
        </div>
        <button
          onClick={handleClose}
          className="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors"
        >
          <X size={18} />
        </button>
      </div>

      {/* ===== BODY (Scrollable) ===== */}
      <div className="space-y-4 flex-1 overflow-y-auto">
        {/* STEP 1: Configure Game Types for Each Level */}
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <label className="form-label mb-3">Configure Levels</label>
          <div className="space-y-2">
            {Array.from({ length: numLevels }, (_, i) => i + 1).map(
              (levelNumber) => (
                <motion.div
                  key={levelNumber}
                  className="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                  initial={{ opacity: 0, x: -10 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: levelNumber * 0.05 }}
                >
                  <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                      <Layers size={16} className="text-purple-main" />
                      <span className="font-medium text-sm text-gray-700 dark:text-gray-300">
                        {t("level")} {levelNumber}
                      </span>
                    </div>

                    <div className="flex gap-2">
                      {/* Box Type Button */}
                      <motion.button
                        type="button"
                        onClick={() => handleGameTypeChange(levelNumber, "box")}
                        className={`px-3 py-1 rounded-lg transition-all duration-200 flex items-center gap-1 text-xs font-medium ${
                          levelGameTypes[levelNumber] === "box"
                            ? "bg-gradient-to-r from-purple-deep to-purple-main text-white shadow-md"
                            : "bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600"
                        }`}
                        whileHover={{ scale: 1.05 }}
                        whileTap={{ scale: 0.95 }}
                      >
                        <Package size={12} />
                        Boxes
                      </motion.button>

                      {/* Balloon Type Button */}
                      <motion.button
                        type="button"
                        onClick={() =>
                          handleGameTypeChange(levelNumber, "balloon")
                        }
                        className={`px-3 py-1 rounded-lg transition-all duration-200 flex items-center gap-1 text-xs font-medium ${
                          levelGameTypes[levelNumber] === "balloon"
                            ? "bg-gradient-to-r from-orange-main to-orange-deep text-white shadow-md"
                            : "bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600"
                        }`}
                        whileHover={{ scale: 1.05 }}
                        whileTap={{ scale: 0.95 }}
                      >
                        <Package size={12} />
                        Balloons
                      </motion.button>
                    </div>
                  </div>
                </motion.div>
              ),
            )}
          </div>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
            Select game type for each level
          </p>
        </motion.div>

        {/* STEP 2: Enter Prompt with integrated attachments (ChatGPT style) */}
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <label className="form-label">{t("enter_prompt")}</label>

          {/* Textarea + Toolbar Container */}
          <div className="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden bg-white dark:bg-gray-800">
            {/* Textarea */}
            <textarea
              value={prompt}
              onChange={(e) => setPrompt(e.target.value)}
              placeholder="e.g., Generate 5 questions about quadratic equations. This prompt will be used for all levels."
              className="w-full h-24 resize-none text-sm p-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 outline-none"
            />

            {/* Bottom Toolbar with Icons */}
            <div className="flex items-center gap-2 px-3 py-2 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
              {/* Upload PDF Button */}
              <label
                className="p-2 text-gray-600 dark:text-gray-400 hover:text-purple-main dark:hover:text-purple-main cursor-pointer transition-colors rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600"
                title="Upload PDF"
              >
                <Upload size={18} />
                <input
                  type="file"
                  accept=".pdf"
                  onChange={handlePDFUpload}
                  className="hidden"
                />
              </label>

              {/* Add Link Button */}
              <button
                type="button"
                onClick={() => {
                  const url = window.prompt("Enter URL:");
                  if (url) {
                    addLinkDirectly(url);
                  }
                }}
                className="p-2 text-gray-600 dark:text-gray-400 hover:text-cyan-600 dark:hover:text-cyan-400 transition-colors rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600"
                title="Add reference link"
              >
                <LinkIcon size={18} />
              </button>

              <div className="flex-1" />

              {/* Show attached items count */}
              {(uploadedPDF || links.length > 0) && (
                <span className="text-xs text-gray-500 dark:text-gray-400">
                  {uploadedPDF ? "📄 1 PDF" : ""}
                  {uploadedPDF && links.length > 0 ? " • " : ""}
                  {links.length > 0
                    ? `🔗 ${links.length} link${links.length > 1 ? "s" : ""}`
                    : ""}
                </span>
              )}
            </div>
          </div>

          {/* Display attached PDFs and Links below textarea */}
          {(uploadedPDF || links.length > 0) && (
            <div className="mt-3 space-y-2">
              {/* PDF Item */}
              {uploadedPDF && (
                <div className="flex items-center justify-between p-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                  <div className="flex items-center gap-2 flex-1">
                    <Package
                      size={14}
                      className="text-green-600 dark:text-green-400"
                    />
                    <span className="text-xs text-green-700 dark:text-green-300 truncate">
                      {uploadedPDF.name}
                    </span>
                  </div>
                  <button
                    onClick={handleRemovePDF}
                    className="p-1 text-red-600 hover:text-red-700 transition-colors ml-2 flex-shrink-0"
                  >
                    <X size={14} />
                  </button>
                </div>
              )}

              {/* Links Items */}
              {links.map((link, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between p-2 bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-800 rounded-lg"
                >
                  <a
                    href={link}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-xs text-cyan-600 dark:text-cyan-400 hover:underline truncate flex-1"
                  >
                    🔗 {link}
                  </a>
                  <button
                    onClick={() => handleRemoveLink(index)}
                    className="p-1 text-red-600 hover:text-red-700 transition-colors ml-2 flex-shrink-0"
                  >
                    <X size={14} />
                  </button>
                </div>
              ))}
            </div>
          )}

          <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
            💡{" "}
            {t("be_specific_for_better_results") ||
              "Be specific for better results"}
          </p>
        </motion.div>

        {/* Summary */}
        <motion.div
          className="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg"
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <p className="text-xs text-blue-700 dark:text-blue-300 font-medium">
            📋 Will generate questions for {numLevels} level
            {numLevels > 1 ? "s" : ""} using{" "}
            {Array.from({ length: numLevels }, (_, i) => i + 1)
              .map((levelNum) => `${levelGameTypes[levelNum]}`)
              .join(", ")}
          </p>
        </motion.div>
      </div>

      {/* ===== GENERATE BUTTON (sticky bottom) ===== */}
      <motion.button
        type="button"
        onClick={handleGenerateAllQuestions}
        disabled={isGenerating || !prompt.trim()}
        className="btn-primary w-full flex items-center justify-center gap-2 mt-6 disabled:opacity-50 disabled:cursor-not-allowed"
        whileHover={{ scale: isGenerating || !prompt.trim() ? 1 : 1.02 }}
        whileTap={{ scale: isGenerating || !prompt.trim() ? 1 : 0.98 }}
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
      >
        {isGenerating ? (
          <>
            <Loader size={16} className="animate-spin" />
            Generating {numLevels} Level{numLevels > 1 ? "s" : ""}...
          </>
        ) : (
          <>
            <Sparkles size={16} />
            Generate All Questions
          </>
        )}
      </motion.button>
    </motion.div>
  );
}

export default AIQuestionGenerator;
