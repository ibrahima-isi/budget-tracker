import { useCurrency } from './useCurrency';

/**
 * Thin wrapper kept for backward-compat with existing call sites.
 * Delegates to useCurrency so formatting always uses the user's active currency.
 */
export function useFormatMoney() {
    const { format } = useCurrency();
    return { format };
}
