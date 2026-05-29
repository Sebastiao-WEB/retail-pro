param(
  [Parameter(Mandatory = $true)]
  [string]$PrinterName,
  [Parameter(Mandatory = $true)]
  [string]$FilePath
)

$ErrorActionPreference = "Stop"

Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class RawPrinterHelper {
  [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
  public class DOCINFO {
    [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
    [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
    [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
  }
  [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
  public static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool ClosePrinter(IntPtr hPrinter);
  [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
  public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] DOCINFO di);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool EndDocPrinter(IntPtr hPrinter);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool StartPagePrinter(IntPtr hPrinter);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool EndPagePrinter(IntPtr hPrinter);
  [DllImport("winspool.drv", SetLastError = true)]
  public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);
  public static bool SendBytesToPrinter(string printerName, byte[] bytes) {
    IntPtr hPrinter;
    if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) return false;
    try {
      DOCINFO di = new DOCINFO();
      di.pDocName = "RetailPro Talao";
      di.pDataType = "RAW";
      if (!StartDocPrinter(hPrinter, 1, di)) return false;
      try {
        if (!StartPagePrinter(hPrinter)) return false;
        try {
          IntPtr unmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
          Marshal.Copy(bytes, 0, unmanagedBytes, bytes.Length);
          int written = 0;
          bool ok = WritePrinter(hPrinter, unmanagedBytes, bytes.Length, out written);
          Marshal.FreeCoTaskMem(unmanagedBytes);
          if (!ok || written != bytes.Length) return false;
        } finally { EndPagePrinter(hPrinter); }
      } finally { EndDocPrinter(hPrinter); }
    } finally { ClosePrinter(hPrinter); }
    return true;
  }
}
"@

if (-not (Test-Path -LiteralPath $FilePath)) {
  throw "Ficheiro RAW nao encontrado: $FilePath"
}

$bytes = [System.IO.File]::ReadAllBytes($FilePath)
$result = [RawPrinterHelper]::SendBytesToPrinter($PrinterName, $bytes)
if (-not $result) {
  throw "Falha ao enviar RAW para a impressora '$PrinterName'."
}
