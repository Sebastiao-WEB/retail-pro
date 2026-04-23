import Swal from "sweetalert2";

export function mostrarToastSwal(mensagem, tipo = "error") {
  return Swal.fire({
    toast: true,
    position: "top-end",
    icon: tipo,
    title: mensagem,
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true,
  });
}

