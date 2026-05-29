const ESC = 0x1b;
const GS = 0x1d;
const LF = 0x0a;

function bytes(...valores) {
  return Buffer.from(valores);
}

function sanitizarTexto(texto) {
  return String(texto ?? "")
    .replace(/\u202F/g, " ")
    .replace(/\u00A0/g, " ")
    .normalize("NFC");
}

function encodeTexto(texto) {
  const normalizado = sanitizarTexto(texto);
  const resultado = [];

  for (const char of normalizado) {
    const code = char.charCodeAt(0);
    if (code <= 0xff) {
      resultado.push(code);
      continue;
    }
    const semAcento = char.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    for (const fallback of semAcento) {
      const fb = fallback.charCodeAt(0);
      if (fb <= 0xff) resultado.push(fb);
    }
  }

  return Buffer.from(resultado);
}

function concat(partes) {
  return Buffer.concat(partes.filter(Boolean));
}

/** Largura segura por papel (fonte A) — evita quebra em termicas com area util menor. */
function larguraColunas(larguraTalao) {
  return larguraTalao === "58mm" ? 32 : 42;
}

function linhaSeparadora(largura) {
  return "-".repeat(largura);
}

function truncar(texto, maximo) {
  const valor = sanitizarTexto(texto);
  if (maximo <= 0) return "";
  if (valor.length <= maximo) return valor;
  if (maximo === 1) return ".";
  return `${valor.slice(0, maximo - 1)}.`;
}

function quebrarLinhas(texto, largura) {
  const palavras = sanitizarTexto(texto).split(/\s+/).filter(Boolean);
  if (!palavras.length) return [""];

  const linhas = [];
  let atual = "";

  for (const palavra of palavras) {
    const candidato = atual ? `${atual} ${palavra}` : palavra;
    if (candidato.length <= largura) {
      atual = candidato;
      continue;
    }
    if (atual) linhas.push(atual);
    atual = palavra.length > largura ? truncar(palavra, largura) : palavra;
  }

  if (atual) linhas.push(atual);
  return linhas;
}

function formatarPrecoCurto(valor) {
  const numero = Number(valor || 0);
  if (!Number.isFinite(numero)) return "0,00";
  const sinal = numero < 0 ? "-" : "";
  const abs = Math.abs(numero);
  const inteiro = Math.floor(abs);
  const centavos = Math.round((abs - inteiro) * 100);
  return `${sinal}${inteiro},${String(centavos).padStart(2, "0")}`;
}

function formatarMoeda(valor) {
  return `${formatarPrecoCurto(valor)} MT`;
}

function formatarIva(valor) {
  const numero = Math.round(Number(valor || 0));
  return `${Number.isFinite(numero) ? numero : 0}%`;
}

function larguraBlocoPreco(largura, detalharIva) {
  if (detalharIva) return largura <= 32 ? 18 : 22;
  return largura <= 32 ? 12 : 14;
}

function montarBlocoPrecoItem(item, largura, detalharIva) {
  const qtd = String(item.quantidade ?? 0);
  const iva = formatarIva(item.ivaPercentual);
  const total = formatarPrecoCurto(item.subtotal);

  if (detalharIva) {
    const ivaMt = formatarPrecoCurto(item.ivaTotal ?? 0);
    return `${qtd} ${iva} ${ivaMt} ${total}`;
  }

  return `${qtd} ${iva} ${total}`;
}

function montarLinhaEsquerdaDireita(esquerda, direita, largura) {
  const larguraDireita = Math.min(direita.length, Math.max(8, Math.floor(largura * 0.38)));
  const direitaFmt = truncar(direita, larguraDireita).padStart(larguraDireita, " ");
  const larguraEsquerda = Math.max(1, largura - larguraDireita);
  const esquerdaFmt = truncar(esquerda, larguraEsquerda).padEnd(larguraEsquerda, " ");
  return `${esquerdaFmt}${direitaFmt}`.slice(0, largura);
}

function linhaItem(item, largura, detalharIva) {
  const direita = montarBlocoPrecoItem(item, largura, detalharIva);
  const linha = montarLinhaEsquerdaDireita(item.nome, direita, largura);
  return linhaTexto(linha);
}

function linhaCabecalhoItens(largura, detalharIva) {
  const direita = detalharIva ? "Qtd IVA IVA$ Total" : "Qtd IVA Total";
  const linha = montarLinhaEsquerdaDireita("Item", direita, largura);
  return linhaTexto(linha);
}

function linhaResumo(rotulo, valor, largura) {
  const linha = montarLinhaEsquerdaDireita(rotulo, valor, largura);
  return linhaTexto(linha);
}

function comandoInicializar() {
  return concat([
    bytes(ESC, 0x40),
    bytes(ESC, 0x4d, 0x00),
  ]);
}

function comandoCodePageLatin() {
  return bytes(ESC, 0x74, 16);
}

function comandoAlinhamento(modo) {
  const mapa = { esquerda: 0, centro: 1, direita: 2 };
  return bytes(ESC, 0x61, mapa[modo] ?? 0);
}

function comandoNegrito(ativo) {
  return bytes(ESC, 0x45, ativo ? 1 : 0);
}

function comandoTamanhoFonte({ largura = false, altura = false } = {}) {
  let valor = 0;
  if (altura) valor |= 0x10;
  if (largura) valor |= 0x20;
  return bytes(GS, 0x21, valor);
}

function comandoResetFormatacao() {
  return concat([
    comandoNegrito(false),
    comandoTamanhoFonte(),
    comandoAlinhamento("esquerda"),
    bytes(ESC, 0x4d, 0x00),
  ]);
}

function comandoAvancoLinhas(linhas) {
  const total = Math.max(0, Math.min(255, Number(linhas || 0)));
  if (!total) return Buffer.alloc(0);
  return bytes(ESC, 0x64, total);
}

function comandoCorteComAvanco(linhasAvanco = 4) {
  return concat([
    comandoResetFormatacao(),
    comandoAvancoLinhas(linhasAvanco),
    bytes(LF, LF, LF),
    bytes(GS, 0x56, 0x01),
  ]);
}

function linhaTexto(texto, opcoes = {}) {
  const linha = truncar(texto, 512);
  const partes = [];
  if (opcoes.alinhamento) partes.push(comandoAlinhamento(opcoes.alinhamento));
  if (opcoes.negrito !== undefined) partes.push(comandoNegrito(!!opcoes.negrito));
  if (opcoes.tamanho) partes.push(comandoTamanhoFonte(opcoes.tamanho));
  partes.push(encodeTexto(`${linha}\n`));
  if (opcoes.tamanho) partes.push(comandoTamanhoFonte());
  if (opcoes.negrito) partes.push(comandoNegrito(false));
  if (opcoes.alinhamento) partes.push(comandoAlinhamento("esquerda"));
  return concat(partes);
}

export function gerarBufferEscpos(talao, opcoes = {}) {
  const largura = larguraColunas(talao?.larguraTalao);
  const corteAutomatico = opcoes.corteAutomatico !== false;
  const linhasAvancoCorte = Math.max(2, Number(opcoes.linhasAvancoCorte || 4));
  const detalharIva = !!talao.detalharIva;
  const venda = talao.venda || {};
  const descontoAplicado = Number(venda.descontoAplicado || 0);

  const partes = [
    comandoInicializar(),
    comandoCodePageLatin(),
    linhaTexto(talao.empresa.nome, { alinhamento: "centro", negrito: true, tamanho: { altura: true } }),
  ];

  if (talao.segundaVia) {
    partes.push(linhaTexto(talao.titulo, { alinhamento: "centro" }));
  }

  if (talao.empresa.nuit) {
    partes.push(linhaTexto(`NUIT: ${talao.empresa.nuit}`, { alinhamento: "centro" }));
  }
  if (talao.empresa.endereco) {
    for (const linha of quebrarLinhas(talao.empresa.endereco, largura)) {
      partes.push(linhaTexto(linha, { alinhamento: "centro" }));
    }
  }
  if (talao.empresa.telefone) {
    partes.push(linhaTexto(talao.empresa.telefone, { alinhamento: "centro" }));
  }
  if (venda.referencia) {
    partes.push(linhaTexto(`Ref: ${venda.referencia}`, { alinhamento: "centro" }));
  }
  partes.push(linhaTexto(`Data: ${venda.dataFormatada}`, { alinhamento: "centro" }));
  partes.push(linhaTexto(linhaSeparadora(largura), { alinhamento: "centro" }));
  partes.push(linhaTexto(`Cliente: ${truncar(venda.cliente, largura - 9)}`));
  partes.push(linhaTexto(`Pagamento: ${truncar(venda.metodoPagamento, largura - 11)}`));
  partes.push(linhaTexto(linhaSeparadora(largura), { alinhamento: "centro" }));
  partes.push(linhaCabecalhoItens(largura, detalharIva));

  for (const item of venda.itens || []) {
    partes.push(linhaItem(item, largura, detalharIva));
  }

  partes.push(linhaTexto(linhaSeparadora(largura), { alinhamento: "centro" }));
  partes.push(linhaResumo("Subtotal", formatarMoeda(venda.subtotal), largura));
  if (detalharIva) {
    partes.push(linhaResumo("Total IVA", formatarMoeda(venda.totalIva), largura));
  }
  if (descontoAplicado > 0) {
    partes.push(linhaResumo("Desconto", `- ${formatarMoeda(descontoAplicado)}`, largura));
  }
  partes.push(linhaResumo("Total", formatarMoeda(venda.total), largura));

  if (venda.pagamentoDinheiro) {
    partes.push(linhaResumo("Valor Pago", formatarMoeda(venda.valorPago), largura));
    partes.push(linhaResumo("Troco", formatarMoeda(venda.troco), largura));
  }

  partes.push(linhaTexto(linhaSeparadora(largura), { alinhamento: "centro" }));
  partes.push(comandoResetFormatacao());

  for (const linha of quebrarLinhas(talao.empresa.rodape, largura)) {
    partes.push(linhaTexto(linha, { alinhamento: "centro" }));
  }

  if (corteAutomatico) {
    partes.push(comandoCorteComAvanco(linhasAvancoCorte));
  }

  return concat(partes);
}

export {
  encodeTexto,
  sanitizarTexto,
  quebrarLinhas,
  montarLinhaEsquerdaDireita,
  montarBlocoPrecoItem,
  formatarPrecoCurto,
  formatarMoeda,
  linhaResumo,
  larguraColunas,
};
