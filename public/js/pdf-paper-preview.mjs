let library;
export async function renderPdfPaper(container, url) {
  const token = {};
  container.pdfPreviewToken = token;
  const status = document.createElement('p');
  status.textContent = 'Loading document…';
  status.setAttribute('role', 'status');
  container.replaceChildren(status);
  try {
    library ||= import('../vendor/pdfjs/pdf.min.mjs');
    const pdfjs = await library;
    pdfjs.GlobalWorkerOptions.workerSrc = new URL('../vendor/pdfjs/pdf.worker.min.mjs', import.meta.url).href;
    const pdf = await pdfjs.getDocument({url, isEvalSupported:false}).promise;
    try {
      for (let number = 1; number <= pdf.numPages; number++) {
        if (container.pdfPreviewToken !== token || !container.isConnected || container.hidden) return;
        const page = await pdf.getPage(number);
        const base = page.getViewport({scale:1});
        const viewport = page.getViewport({scale:Math.min(1.5,1200/base.width)});
        const canvas = document.createElement('canvas');
        canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height);
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', `Document page ${number} of ${pdf.numPages}`);
        await page.render({canvasContext:canvas.getContext('2d'),viewport,annotationMode:pdfjs.AnnotationMode.DISABLE}).promise;
        if (container.pdfPreviewToken !== token) return;
        if (number === 1) container.replaceChildren();
        container.append(canvas);
        page.cleanup();
      }
    } finally { await pdf.destroy(); }
  } catch {
    if (container.pdfPreviewToken === token) status.textContent = 'This PDF could not be displayed. Please use an unlocked, valid PDF.';
  }
}
