// Payment checks shared by the Payments page and the finance home page (sample data until fees are confirmed)

/** Not paid yet: waiting for payment, or the slip was rejected. */
export const isUnpaid = (row) => ['awaiting_payment', 'slip_rejected'].includes(row.status);

/** Days past the payment period (issued + dueDays), 0 when not overdue or already paid / being checked. */
export function overdueDays(row, dueDays) {
    return isUnpaid(row) ? Math.max(0, Math.floor((Date.now() - new Date(row.issuedAt)) / 86400000) - dueDays) : 0;
}

/** The same slip used more than once (same bank, transfer time and amount): { [id]: [other refs] }. */
export function duplicateSlips(rows) {
    const byKey = {};
    rows.forEach((row) => {
        if (row.slip) (byKey[`${row.slip.bank}|${row.slip.transferredAt}|${row.slip.amount}`] ??= []).push(row);
    });
    const map = {};
    Object.values(byKey)
        .filter((group) => group.length > 1)
        .forEach((group) => group.forEach((row) => (map[row.id] = group.filter((other) => other !== row).map((other) => other.ref))));
    return map;
}
