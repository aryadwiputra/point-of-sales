import React, { createContext, useContext, useState, useEffect } from 'react';

const ThemeSwitcher = createContext();

export const ThemeSwitcherProvider = ({ children }) => {
    // define state darkMode
    const [darkMode, setDarkMode] = useState(
        localStorage.getItem('darkMode') === 'true'
    )

    useEffect(() => {
        const root = document.documentElement;
        const toggleTransition = () => {
            root.classList.add('no-transition');
            setTimeout(() => {
                root.classList.remove('no-transition');
            }, 0);
        };

        toggleTransition();

        if (darkMode)
            document.body.classList.add('dark');
        else
            document.body.classList.remove('dark');

        // set darkMode in localstorage
        localStorage.setItem('darkMode', darkMode);
    }, [darkMode]);

    const themeSwitcher = () => setDarkMode(!darkMode);

    return (
        <ThemeSwitcher.Provider value={{ darkMode, themeSwitcher }}>
            {children}
        </ThemeSwitcher.Provider>
    )
}

export const useTheme = () => useContext(ThemeSwitcher);
