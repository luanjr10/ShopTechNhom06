/**
 * Chart.js (LineChart01/BarChart01/BarChart02 của template) đọc nhãn trục X
 * theo `time.parser: 'MM-DD-YYYY'` — backend trả ISO ('YYYY-MM-DD') hoặc
 * tháng ('MM-YYYY'), cần đổi định dạng trước khi đưa vào chartData.
 */
export function isoDateToChartLabel(isoDate: string): string {
  const [y, m, d] = isoDate.split("-");
  return `${m}-${d}-${y}`;
}

export function monthLabelToChartLabel(monthLabel: string): string {
  const [m, y] = monthLabel.split("-");
  return `${m}-01-${y}`;
}
