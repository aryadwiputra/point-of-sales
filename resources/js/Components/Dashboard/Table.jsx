import React from 'react'

const Card = ({ icon, title, className, children }) => {
    return (
        <>
            <div className={`p-4 rounded-t-card border ${className || ""} bg-white dark:bg-canvas-night-elevated border-hairline-light dark:border-hairline-dark shadow-paper`}>
                <div className='flex items-center gap-2 font-semibold text-sm text-ink dark:text-gray-200'>
                    {title}
                </div>
            </div>
            <div className='bg-white dark:bg-canvas-night-elevated rounded-b-card border-t-0 dark:border-hairline-dark'>
                {children}
            </div>
        </>

    )
}

const Table = ({ children }) => {
    return (
        <div className="w-full overflow-hidden overflow-x-auto border-collapse rounded-b-card border border-t-0 border-hairline-light dark:border-hairline-dark shadow-paper">
            <table className="w-full text-sm">
                {children}
            </table>
        </div>
    );
};

const Thead = ({ className, children }) => {
    return (
        <thead className={`${className || ""} border-b border-hairline-light bg-canvas-cream dark:border-hairline-dark dark:bg-canvas-night`}>{children}</thead>
    );
};

const Tbody = ({ className, children }) => {
    return (
        <tbody className={`${className || ""} divide-y divide-hairline-light bg-white dark:divide-hairline-dark dark:bg-canvas-night-elevated`}>
            {children}
        </tbody>
    );
};

const Td = ({ className, children }) => {
    return (
        <td
            className={`${className || ""} whitespace-nowrap p-4 align-middle text-shade-70 dark:text-gray-300`}
        >
            {children}
        </td>
    );
};

const Th = ({ className, children }) => {
    return (
        <th
            scope="col"
            className={`${className || ""} h-12 px-4 text-left align-middle font-semibold text-shade-60 dark:text-gray-300`}
        >
            {children}
        </th>
    );
};

const Empty = ({ colSpan, message, children }) => {
    return (
        <tr>
            <td colSpan={colSpan}>
                <div className="flex items-center justify-center h-96">
                    <div className="text-center">
                        {children}
                        <div className="mt-5">
                            {message}
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    )
}

Table.Card = Card;
Table.Thead = Thead;
Table.Tbody = Tbody;
Table.Td = Td;
Table.Th = Th;
Table.Empty = Empty;

export default Table;
