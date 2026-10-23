# Generador del Manual de referencia de APJ

El manual (`MANUAL DE REFERENCIA DE APJ 2.docx` y `.pdf`, en la raíz del repositorio)
se genera desde estos archivos; no se edita a mano en Word.

- `content1.js` … `content4.js`: el texto, en bloques (`h1`, `h2`, `h3`, `p`, `code`, `ul`,
  `ol`, `table`, `note`, `warn`, `img`, `api`). En el texto, `` `codigo` `` y `**negrita**`.
- `build.js`: arma `manual.docx` (con docx-js) y `manual.html`.
- `pdf.js`: convierte `manual.html` en `manual.pdf` con Microsoft Edge.
- `shots/`: capturas de los diálogos. `img/logo.jpeg`: logo de la portada.

## Regenerar

```
npm install
npm run build
```

Luego copie `manual.docx` y `manual.pdf` a la raíz como `MANUAL DE REFERENCIA DE APJ 2.docx`
y `.pdf`. Al abrir el `.docx`, Word ofrece actualizar el índice (responda que sí).
