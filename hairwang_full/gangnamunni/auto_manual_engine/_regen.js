const fs = require("fs");
const path = require("path");
const { renderBookHTML } = require("./book-html-renderer");
const bookDir = "../uploads/book/20260226_021903";
const meta = JSON.parse(fs.readFileSync(path.join(bookDir, "meta.json"), "utf8"));
const results = JSON.parse(fs.readFileSync(path.join(bookDir, "results.json"), "utf8"));
const bookData = {
    title: meta.title || "Seoul Beauty Screenshot Book",
    subtitle: meta.subtitle || "",
    generatedAt: meta.generatedAt || meta.completedAt,
    chapters: meta.chapters || [],
    pages: results.pages || results,
    stats: meta.stats || {},
    config: meta.config || {}
};
const html = renderBookHTML(bookData, "screenshots");
fs.writeFileSync(path.join(bookDir, "book.html"), html, "utf8");
console.log("OK size=" + html.length);

