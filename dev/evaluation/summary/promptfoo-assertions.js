function textLength(value) {
  return Array.from(String(value || '').trim()).length;
}

function pass(score, reason) {
  return { pass: true, score, reason };
}

function fail(reason) {
  return { pass: false, score: 0, reason };
}

function outputText(output) {
  return String(output || '').trim();
}

function notGenerationError(output) {
  const text = outputText(output);
  if (!text || text.startsWith('ERROR:')) {
    return fail('No usable summary candidate was returned.');
  }
  return pass(1, 'Summary candidate exists.');
}

function validChineseExcerptLength(output) {
  const length = textLength(output);
  if (length < 50) {
    return fail(`Too short for a Chinese WordPress excerpt: ${length} chars.`);
  }
  if (length > 160) {
    return fail(`Too long for a Chinese WordPress excerpt: ${length} chars.`);
  }
  if (length >= 70 && length <= 140) {
    return pass(1, `Preferred length band: ${length} chars.`);
  }
  return { pass: true, score: 0.7, reason: `Acceptable but outside preferred 70-140 band: ${length} chars.` };
}

function noMetaFraming(output) {
  const text = outputText(output);
  const banned = ['本文说明', '本文介绍', '这篇文章', '该文章', '这篇草稿主张', '草稿', 'this article', 'this draft'];
  const hit = banned.find((phrase) => text.toLowerCase().includes(phrase.toLowerCase()));
  if (hit) {
    return fail(`Contains meta framing: ${hit}`);
  }
  return pass(1, 'No draft/article meta framing.');
}

function openingQuality(output) {
  const text = outputText(output).replace(/\s+/g, '');
  if (/^(面向|适合|需要|想|不想|针对)/u.test(text)) {
    return { pass: true, score: 0.5, reason: 'Starts with a formulaic audience-label opening.' };
  }
  return pass(1, 'Opening is not a formulaic audience label.');
}

module.exports = {
  notGenerationError,
  validChineseExcerptLength,
  noMetaFraming,
  openingQuality,
};
