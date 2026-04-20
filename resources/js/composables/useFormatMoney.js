const formatter = new Intl.NumberFormat('fr-FR', {
    style:    'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
});

export function useFormatMoney() {
    return {
        format: (value) => formatter.format(Number(value) || 0),
    };
}
