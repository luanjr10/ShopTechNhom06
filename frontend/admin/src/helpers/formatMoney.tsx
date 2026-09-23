export const formatMoneyVietNam = (amount: Number) => {
    return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
    }).format(Number(amount));
}