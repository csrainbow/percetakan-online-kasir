function cetakNota() {
    if (window.AndroidPrint && typeof window.AndroidPrint.choosePrint === 'function') {
        window.AndroidPrint.choosePrint();
    } else if (window.AndroidPrint && typeof window.AndroidPrint.print === 'function') {
        window.AndroidPrint.print();
    } else {
        window.print();
    }
}
