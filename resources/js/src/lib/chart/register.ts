/**
 * Single Chart.js entry: registers scales/controllers once so every chart shares
 * the same Chart constructor (avoids "category is not a registered scale" when
 * Vite bundles multiple chart.js copies from different import paths).
 */
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export { Chart };
