import { StrictMode } from 'react';
import ReactDOM from 'react-dom/client';
import App from './spa/App';
import '../scss/app.scss';

ReactDOM.createRoot(document.getElementById('root')).render(
    <StrictMode>
        <App />
    </StrictMode>,
);
