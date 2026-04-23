export function calcularDiferencaProjetada({ dinheiroReal, dinheiroEsperado }) {
  if (dinheiroReal === null || dinheiroReal === undefined || dinheiroReal === "") return null;
  const real = Number(dinheiroReal);
  const esperado = Number(dinheiroEsperado || 0);
  if (!Number.isFinite(real) || !Number.isFinite(esperado)) return null;
  return real - esperado;
}

