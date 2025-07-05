// import {
//   reactExtension,
//   useApi,
//   AdminBlock,
//   BlockStack,
//   Text,
//   InlineStack,
//   Link,
// } from '@shopify/ui-extensions-react/admin';
// import { useEffect, useState } from 'react';

// const TARGET = 'admin.order-details.block.render';

// export default reactExtension(TARGET, () => <App />);

// function App() {
//   const { data } = useApi(TARGET);
//   const orderGID = data?.selected?.[0]?.id;
//   const orderId = orderGID?.split('/')?.pop();

//   const [prescriberLogs, setPrescriberLogs] = useState([]);
//   const [checkerLogs, setCheckerLogs] = useState([]);
//   const [pdfLink, setPdfLink] = useState(null);
//   const [loading, setLoading] = useState(true);

//   useEffect(() => {
//     if (!orderId) return;

//     const fetchLogs = async () => {
//       setLoading(true);
//       try {
//         const response = await fetch(
//           'https://rightangled.24livehost.com/api/prescriber/audit-logs/order',
//           {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ order_id: orderId }),
//           }
//         );

//         const result = await response.json();
//         setPrescriberLogs(result?.prescriber_logs || []);
//         setCheckerLogs(result?.checker_logs || []);
//         setPdfLink(result?.prescribed_pdf || null);
//       } catch (error) {
//         console.error('❌ Error fetching logs:', error);
//       } finally {
//         setLoading(false);
//       }
//     };

//     fetchLogs();
//   }, [orderId]);



//   const renderLogs = (logs, title, showPdf = false) => (
//     <AdminBlock title={title}>
//       <BlockStack
//         spacing="loose"
//         style={{
//           maxHeight: '100px', // Adjust height as needed
//           overflowY: 'auto',
//           paddingRight: '8px', // Add right padding to avoid hiding scrollbar
//         }}
//       >
//         {logs.map((log) => (
//           <BlockStack key={log.id} spacing="tight">
//             <Text tone="subdued">{log.details}</Text>
//             <Text size="small" tone="secondary">Action: {log.action}</Text>
//           </BlockStack>
//         ))}

//         {showPdf && pdfLink && (
//           <InlineStack>
//             <Text size="medium" emphasis="bold">Prescribed PDF:</Text>
//             <Link to={pdfLink} target="_blank">🔗 View PDF</Link>
//           </InlineStack>
//         )}
//       </BlockStack>
//     </AdminBlock>
//   );


//   if (loading) {
//     return (
//       <AdminBlock title="Audit Logs">
//         <Text>Loading logs...</Text>
//       </AdminBlock>
//     );
//   }

//   return (
//     <>
//       {prescriberLogs.length > 0 && renderLogs(prescriberLogs, 'Prescriber Audit Logs', true)}
//       {checkerLogs.length > 0 && renderLogs(checkerLogs, 'Checker Audit Logs')}
//     </>
//   );
// }

import {
  reactExtension,
  useApi,
  AdminBlock,
  BlockStack,
  InlineStack,
  Text,
  Link,
  Divider,
  Badge,
  Box,
} from '@shopify/ui-extensions-react/admin';
import { useEffect, useState } from 'react';

const TARGET = 'admin.order-details.block.render';

export default reactExtension(TARGET, () => <App />);

function App() {
  const { data } = useApi(TARGET);
  const orderGID = data?.selected?.[0]?.id;
  const orderId = orderGID?.split('/')?.pop();

  const [prescriberLogs, setPrescriberLogs] = useState([]);
  const [checkerLogs, setCheckerLogs] = useState([]);
  const [adminLogs, setAdminLogs] = useState([]);
  const [pdfLink, setPdfLink] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!orderId) return;

    const fetchLogs = async () => {
      setLoading(true);
      try {
        const response = await fetch(
          'https://d8a9-136-232-169-245.ngrok-free.app/api/prescriber/audit-logs/order',
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId }),
          }
        );

        const result = await response.json();
        setPrescriberLogs(result?.prescriber_logs || []);
        setCheckerLogs(result?.checker_logs || []);
        setAdminLogs(result?.admin_logs || []);
        setPdfLink(result?.prescribed_pdf || null);
      } catch (error) {
        console.error('❌ Error fetching logs:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchLogs();
  }, [orderId]);

  let emoji = '🔷';
  const renderTimeline = (logs) => {
    return logs.map((log, index) => {
    
      return (
            <BlockStack spacing="loose">
                <Box
                    maxHeight="250px"
                    overflow="auto"
                    paddingInlineEnd="200"
                  >
              <Text tone="subdued" fontWeight="medium">
                {log.details}
              </Text>
              <Text tone="info" size="small">
                 <Text as="span" fontWeight="bold">Action</Text>: {log.action}
              </Text>
              {log.checker_pdf_url && (
                <InlineStack gap="tight" align="center">
                  <Text size="small" tone="subdued">
                    Attachment:
                  </Text>
                  <Link to={log.checker_pdf_url} target="_blank">
                    🔗 View PDF
                  </Link>
                </InlineStack>
              )}
          {index < logs.length - 1 && <Divider />}
          </Box>
        </BlockStack>
      );
    });
  };

  return (
    <AdminBlock title="Audit Logs" spacing="loose">
      <BlockStack spacing="loose">
        {loading && <Text>Loading logs...</Text>}

        {!loading && adminLogs.length > 0 && (
          <>
            <Text emphasis="bold" size="medium">
              {emoji} Admin Audit Logs
            </Text>
            {renderTimeline(adminLogs)}
          </>
        )}

        {!loading && prescriberLogs.length > 0 && (
          <>
            <Text emphasis="bold" size="medium">
              {emoji} Prescriber Audit Logs
            </Text>
            {renderTimeline(prescriberLogs)}

            {pdfLink && (
              <InlineStack gap="tight" align="center">
                <Text size="small" emphasis="bold">
                  Prescribed PDF:
                </Text>
                <Link to={pdfLink} target="_blank">
                  🔗 View PDF
                </Link>
              </InlineStack>
            )}
          </>
        )}

        {!loading && checkerLogs.length > 0 && (
          <>
            <Text emphasis="bold" size="medium">
              {emoji} Checker Audit Logs
            </Text>
            {renderTimeline(checkerLogs)}
          </>
        )}
      </BlockStack>
    </AdminBlock>
  );
}


// import {
//   reactExtension,
//   useApi,
//   AdminBlock,
//   BlockStack,
//   InlineStack,
//   Text,
//   Link,
//   Divider,
//   Box,
//   Button,
// } from '@shopify/ui-extensions-react/admin';
// import { useEffect, useState } from 'react';

// const TARGET = 'admin.order-details.block.render';

// export default reactExtension(TARGET, () => <App />);

// function App() {
//   const { data } = useApi(TARGET);
//   const orderGID = data?.selected?.[0]?.id;
//   const orderId = orderGID?.split('/')?.pop();

//   const [prescriberLogs, setPrescriberLogs] = useState([]);
//   const [checkerLogs, setCheckerLogs] = useState([]);
//   const [adminLogs, setAdminLogs] = useState([]);
//   const [pdfLink, setPdfLink] = useState(null);
//   const [loading, setLoading] = useState(true);

//   // Pagination state
//   const pageSize = 3;
//   const [currentPage, setCurrentPage] = useState(1);

//   useEffect(() => {
//     if (!orderId) return;

//     const fetchLogs = async () => {
//       setLoading(true);
//       try {
//         const response = await fetch(
//           'https://d8a9-136-232-169-245.ngrok-free.app/api/prescriber/audit-logs/order',
//           {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ order_id: orderId }),
//           }
//         );

//         const result = await response.json();
//         setPrescriberLogs(result?.prescriber_logs || []);
//         setCheckerLogs(result?.checker_logs || []);
//         setAdminLogs(result?.admin_logs || []);
//         setPdfLink(result?.prescribed_pdf || null);
//       } catch (error) {
//         console.error('❌ Error fetching logs:', error);
//       } finally {
//         setLoading(false);
//       }
//     };

//     fetchLogs();
//   }, [orderId]);

//   // Combine all logs into one timeline
//   const allLogs = [
//     ...(adminLogs || []).map(log => ({ ...log, section: 'Admin' })),
//     ...(prescriberLogs || []).map(log => ({ ...log, section: 'Prescriber' })),
//     ...(checkerLogs || []).map(log => ({ ...log, section: 'Checker' })),
//   ];

//   const pageCount = Math.ceil(allLogs.length / pageSize);

//   const logsToShow = allLogs.slice(
//     (currentPage - 1) * pageSize,
//     currentPage * pageSize
//   );

//   const handlePrev = () => {
//     setCurrentPage(prev => Math.max(1, prev - 1));
//   };

//   const handleNext = () => {
//     setCurrentPage(prev => Math.min(pageCount, prev + 1));
//   };

//   return (
//     <AdminBlock title="Audit Logs">
//       {loading ? (
//         <Text>Loading logs...</Text>
//       ) : (
//         <>
//           <Box
//             maxHeight="320px"
//             overflow="auto"
//             paddingInlineEnd="200"
//           >
//             <BlockStack spacing="loose">
//               {logsToShow.map((log, index) => (
//                 <BlockStack key={index} spacing="tight">
//                   <Text emphasis="bold" size="small">
//                     {log.section} Audit Log
//                   </Text>
//                   <Text tone="subdued" fontWeight="medium">
//                     {log.details}
//                   </Text>
//                   <InlineStack gap="tight">
//                     <Text tone="info" size="small" fontWeight="bold">
//                       Action:
//                     </Text>
//                     <Text tone="info" size="small">{log.action}</Text>
//                   </InlineStack>
//                   {log.checker_pdf_url && (
//                     <InlineStack gap="tight" align="center">
//                       <Text size="small" tone="subdued">
//                         Attachment:
//                       </Text>
//                       <Link to={log.checker_pdf_url} target="_blank">
//                         🔗 View PDF
//                       </Link>
//                     </InlineStack>
//                   )}
//                   {index < logsToShow.length - 1 && <Divider />}
//                 </BlockStack>
//               ))}
//             </BlockStack>
//           </Box>

//           {/* Pagination controls */}
//           {pageCount > 1 && (
//             <InlineStack gap="tight" align="center" marginBlockStart="200">
//               <Button
//                 kind="plain"
//                 disabled={currentPage === 1}
//                 onPress={handlePrev}
//               >
//                 ◀️
//               </Button>
//               <Text size="small">
//                 Page {currentPage} of {pageCount}
//               </Text>
//               <Button
//                 kind="plain"
//                 disabled={currentPage === pageCount}
//                 onPress={handleNext}
//               >
//                 ▶️
//               </Button>
//             </InlineStack>
//           )}
//         </>
//       )}

//       {pdfLink && (
//         <InlineStack gap="tight" align="center" marginBlockStart="200">
//           <Text size="small" emphasis="bold">
//             Prescribed PDF:
//           </Text>
//           <Link to={pdfLink} target="_blank">
//             🔗 View PDF
//           </Link>
//         </InlineStack>
//       )}
//     </AdminBlock>
//   );
// }


// import {
//   reactExtension,
//   useApi,
//   AdminBlock,
//   BlockStack,
//   InlineStack,
//   Text,
//   Link,
//   Divider,
//   Box,
//   Button,
// } from '@shopify/ui-extensions-react/admin';
// import { useEffect, useState } from 'react';

// const TARGET = 'admin.order-details.block.render';

// export default reactExtension(TARGET, () => <App />);

// function App() {
//   const { data } = useApi(TARGET);
//   const orderGID = data?.selected?.[0]?.id;
//   const orderId = orderGID?.split('/')?.pop();

//   const [prescriberLogs, setPrescriberLogs] = useState([]);
//   const [checkerLogs, setCheckerLogs] = useState([]);
//   const [adminLogs, setAdminLogs] = useState([]);
//   const [pdfLink, setPdfLink] = useState(null);
//   const [loading, setLoading] = useState(true);

//   // Pagination state
//   const pageSize = 3;
//   const [currentPage, setCurrentPage] = useState(1);

//   useEffect(() => {
//     if (!orderId) return;

//     const fetchLogs = async () => {
//       setLoading(true);
//       try {
//         const response = await fetch(
//           'https://d8a9-136-232-169-245.ngrok-free.app/api/prescriber/audit-logs/order',
//           {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ order_id: orderId }),
//           }
//         );

//         const result = await response.json();
//         setPrescriberLogs(result?.prescriber_logs || []);
//         setCheckerLogs(result?.checker_logs || []);
//         setAdminLogs(result?.admin_logs || []);
//         setPdfLink(result?.prescribed_pdf || null);
//       } catch (error) {
//         console.error('❌ Error fetching logs:', error);
//       } finally {
//         setLoading(false);
//       }
//     };

//     fetchLogs();
//   }, [orderId]);

//   // Combine all logs into one timeline
//   const allLogs = [
//     ...(adminLogs || []).map(log => ({ ...log, section: 'Admin' })),
//     ...(prescriberLogs || []).map(log => ({ ...log, section: 'Prescriber' })),
//     ...(checkerLogs || []).map(log => ({ ...log, section: 'Checker' })),
//   ];

//   const pageCount = Math.ceil(allLogs.length / pageSize);

//   const logsToShow = allLogs.slice(
//     (currentPage - 1) * pageSize,
//     currentPage * pageSize
//   );

//   const handlePrev = () => {
//     setCurrentPage(prev => Math.max(1, prev - 1));
//   };

//   const handleNext = () => {
//     setCurrentPage(prev => Math.min(pageCount, prev + 1));
//   };

//   return (
//     <AdminBlock title="Audit Logs">
//       {loading ? (
//         <Text>Loading logs...</Text>
//       ) : (
//         <>
//           {/* 🟢 PDF LINK goes here, ABOVE logs */}
//           {pdfLink && (
//             <InlineStack gap="tight" align="center" marginBlockEnd="200">
//               <Text size="small" emphasis="bold">
//                 Prescribed PDF:
//               </Text>
//               <Link to={pdfLink} target="_blank">
//                 🔗 View PDF
//               </Link>
//             </InlineStack>
//           )}

//           <Box
//             minHeight="320"
//             maxHeight="320px"
//             overflow="auto"
//             paddingInlineEnd="200"
//           >
//             <BlockStack spacing="loose">
//               {logsToShow.map((log, index) => (
//                 <BlockStack key={index} spacing="tight">
//                   <Text emphasis="bold" size="small">
//                     {log.section} Audit Log
//                   </Text>
//                   <Text tone="subdued" fontWeight="medium">
//                     {log.details}
//                   </Text>
//                   <InlineStack gap="tight">
//                     <Text tone="info" size="small" fontWeight="bold">
//                       Action:
//                     </Text>
//                     <Text tone="info" size="small">{log.action}</Text>
//                   </InlineStack>
//                   {log.checker_pdf_url && (
//                     <InlineStack gap="tight" align="center">
//                       <Text size="small" tone="subdued">
//                         Attachment:
//                       </Text>
//                       <Link to={log.x} target="_blank">
//                         🔗 View PDF
//                       </Link>
//                     </InlineStack>
//                   )}
//                   {index < logsToShow.length - 1 && <Divider />}
//                 </BlockStack>
//               ))}
//             </BlockStack>
//           </Box>

//           {/* Pagination controls */}
//           {pageCount > 1 && (
//             <InlineStack gap="tight" align="center" marginBlockStart="200">
//               <Button
//                 kind="plain"
//                 disabled={currentPage === 1}
//                 onPress={handlePrev}
//               >
//                 ◀️
//               </Button>
//               <Text size="small">
//                 Page {currentPage} of {pageCount}
//               </Text>
//               <Button
//                 kind="plain"
//                 disabled={currentPage === pageCount}
//                 onPress={handleNext}
//               >
//                 ▶️
//               </Button>
//             </InlineStack>
//           )}
//         </>
//       )}
//     </AdminBlock>
//   );
// }
