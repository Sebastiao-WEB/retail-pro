export function arredondarMoeda(valor) {
  const numero = Number(valor);
  if (!Number.isFinite(numero)) return 0;
  return Number(numero.toFixed(2));
}

export function calcularDiferencaProjetada({ dinheiroReal, dinheiroEsperado }) {
  if (dinheiroReal === null || dinheiroReal === undefined || dinheiroReal === "") return null;
  const real = arredondarMoeda(dinheiroReal);
  const esperado = arredondarMoeda(dinheiroEsperado || 0);
  if (!Number.isFinite(real) || !Number.isFinite(esperado)) return null;
  return arredondarMoeda(real - esperado);
}

