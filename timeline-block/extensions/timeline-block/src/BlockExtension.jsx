
//working code
// import {
//   reactExtension,
//   useApi,
//   AdminBlock,
//   BlockStack,
//   InlineStack,
//   Text,
//   Link,
//   Divider,
//   Badge,
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

//   useEffect(() => {
//     if (!orderId) return;

//     const fetchLogs = async () => {
//       setLoading(true);
//       try {
//         const response = await fetch(
//           // 'https://d8a9-136-232-169-245.ngrok-free.app/api/prescriber/audit-logs/order',
//           'https://c6cb-136-232-169-245.ngrok-free.app/api/prescriber/audit-logs/order',
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

//   let emoji = '🔷';
//   const renderTimeline = (logs) => {
//     return logs.map((log, index) => {

//       return (
//             <BlockStack spacing="extraTight">
//               <Text tone="subdued" fontWeight="medium">
//                 {log.details}
//               </Text>
//               <Text tone="info" size="small">
//                  <Text as="span" fontWeight="bold">Action</Text>: {log.action}
//               </Text>
//               {log.checker_pdf_url && (
//                 <InlineStack gap="tight" align="center">
//                   <Text size="small" tone="subdued">
//                     Attachment:
//                   </Text>
//                   <Link to={log.checker_pdf_url} target="_blank">
//                     🔗 View PDF
//                   </Link>
//                 </InlineStack>
//               )}
//           {index < logs.length - 1 && <Divider />}
//         </BlockStack>
//       );
//     });
//   };

//   return (
//     <AdminBlock title="Audit Logs">
//       <BlockStack spacing="loose" style={{ maxHeight: '250px', overflowY: 'auto' }}>
//         {loading && <Text>Loading logs...</Text>}

//         {!loading && adminLogs.length > 0 && (
//           <>
//             <Text emphasis="bold" size="medium">
//               {emoji} Admin Audit Logs
//             </Text>
//             {renderTimeline(adminLogs)}
//           </>
//         )}

//         {!loading && prescriberLogs.length > 0 && (
//           <>
//             <Text emphasis="bold" size="medium">
//               {emoji} Prescriber Audit Logs
//             </Text>
//             {renderTimeline(prescriberLogs)}

//             {pdfLink && (
//               <InlineStack gap="tight" align="center">
//                 <Text size="small" emphasis="bold">
//                   Prescribed PDF:
//                 </Text>
//                 <Link to={pdfLink} target="_blank">
//                   🔗 View PDF
//                 </Link>
//               </InlineStack>
//             )}
//           </>
//         )}

//         {!loading && checkerLogs.length > 0 && (
//           <>
//             <Text emphasis="bold" size="medium">
//               {emoji} Checker Audit Logs
//             </Text>
//             {renderTimeline(checkerLogs)}
//           </>
//         )}
//       </BlockStack>
//     </AdminBlock>
//   );
// }
// new code 08-07

// import {
//   reactExtension,
//   useApi,
//   AdminBlock,
//   BlockStack,
//   InlineStack,
//   Text,
//   Link,
//   Divider,
// } from '@shopify/ui-extensions-react/admin';
// import { useEffect, useState } from 'react';

// const TARGET = 'admin.order-details.block.render';

// export default reactExtension(TARGET, () => <App />);

// function App() {
//   const { data } = useApi(TARGET);
//   const orderGID = data?.selected?.[0]?.id;
//   const orderId = orderGID?.split('/')?.pop();

//   const [logs, setLogs] = useState([]);
//   const [loading, setLoading] = useState(true);

//   useEffect(() => {
//     if (!orderId) return;

//     const fetchLogs = async () => {
//       setLoading(true);
//       try {
//         const response = await fetch(
//           'https://c6cb-136-232-169-245.ngrok-free.app/api/prescriber/audit-logs/order',
//           {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ order_id: orderId }),
//           }
//         );

//         const result = await response.json();
//         setLogs(result?.logs || []);
//       } catch (error) {
//         console.error('❌ Error fetching logs:', error);
//       } finally {
//         setLoading(false);
//       }
//     };

//     fetchLogs();
//   }, [orderId]);

//   const renderLogs = () => {
//     return logs.map((log, index) => (
//       <BlockStack key={log.id} spacing="extraTight">
//         <Text tone="subdued">
//         {/* {log.user_name} ({log.role_name}) */}
//         </Text>
//         <Text>{log.details}</Text>
//         <Text size="small" tone="info">
//           <Text as="span" fontWeight="bold">Action:</Text> {log.action}
//         </Text>

//         {log.checker_pdf_url && (
//           <InlineStack gap="tight" align="center">
//             <Text size="small" tone="subdued">Attachment:</Text>
//             <Link to={log.checker_pdf_url} target="_blank">📎 View PDF</Link>
//           </InlineStack>
//         )}

//         {log.prescribed_pdf_url && (
//           <InlineStack gap="tight" align="center">
//             <Text size="small" tone="subdued">Prescribed PDF:</Text>
//             <Link to={log.prescribed_pdf_url} target="_blank">📎 View PDF</Link>
//           </InlineStack>
//         )}

//         {index < logs.length - 1 && <Divider />}
//       </BlockStack>
//     ));
//   };

//   return (
//     <AdminBlock title="Order Timeline">
//       <BlockStack spacing="loose" style={{ maxHeight: '300px', overflowY: 'auto' }}>
//         {loading ? <Text>Loading logs...</Text> : renderLogs()}
//       </BlockStack>
//     </AdminBlock>
//   );
// }

import {
  reactExtension,
  useApi,
  AdminBlock,
  BlockStack,
  Box,
  Button,
  InlineStack,
  Text,
  Link,
  Divider,
} from '@shopify/ui-extensions-react/admin';
import { useEffect, useState } from 'react';

const TARGET = 'admin.order-details.block.render';

export default reactExtension(TARGET, () => <App />);

function App() {
  const { data } = useApi(TARGET);
  const orderGID = data?.selected?.[0]?.id;
  const orderId = orderGID?.split('/')?.pop();

  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const logsPerPage = 5;

  useEffect(() => {
    if (!orderId) return;

    const fetchLogs = async () => {
      setLoading(true);
      try {
        const response = await fetch(
          'https://rightangled.24livehost.com/api/prescriber/audit-logs/order',
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId }),
          }
        );

        const result = await response.json();
        setLogs(result?.logs || []);
      } catch (error) {
        console.error('❌ Error fetching logs:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchLogs();
  }, [orderId]);

  const totalPages = Math.ceil(logs.length / logsPerPage);
  const indexOfLast = currentPage * logsPerPage;
  const indexOfFirst = indexOfLast - logsPerPage;
  const currentLogs = logs.slice(indexOfFirst, indexOfLast);

  const renderLogs = () => {
    return currentLogs.map((log, index) => (
      <BlockStack key={log.id} spacing="extraTight">
        <Text tone="subdued">
          {/* <strong>{log.user_name}</strong> ({log.role_name}) */}
        </Text>
        <Text>{log.details}</Text>
        <Text size="small" tone="info">
          <Text as="span" fontWeight="bold">Action:</Text> {log.action}
        </Text>

        {log.checker_pdf_url && (
          <InlineStack gap="tight" align="center">
            <Text size="small" tone="subdued">Attachment:</Text>
            <Link to={log.checker_pdf_url} target="_blank">📎 View PDF</Link>
          </InlineStack>
        )}

        {log.prescribed_pdf_url && (
          <InlineStack gap="tight" align="center">
            <Text size="small" tone="subdued">Prescribed PDF:</Text>
            <Link to={log.prescribed_pdf_url} target="_blank">📎 View PDF</Link>
          </InlineStack>
        )}

        {index < currentLogs.length - 1 && <Divider />}
      </BlockStack>
    ));
  };

  const PaginationControls = () => (
    <InlineStack gap="tight" alignment="center" blockAlignment="center">
      <Button
        variant="secondary"
        disabled={currentPage === 1}
        onPress={() => setCurrentPage((prev) => prev - 1)}
      >
        ◀ Previous
      </Button>
      <Text size="small">
        Page {currentPage} of {totalPages}
      </Text>
      <Button
        variant="secondary"
        disabled={currentPage === totalPages}
        onPress={() => setCurrentPage((prev) => prev + 1)}
      >
        Next ▶
      </Button>
    </InlineStack>
  );

  return (
    <AdminBlock title="Order Timeline">
      <Box padding="base">
        <BlockStack spacing="loose">
          {loading ? (
            <Text>Loading logs...</Text>
          ) : logs.length === 0 ? (
            <Text>No logs available.</Text>
          ) : (
            <>
              {renderLogs()}
              {totalPages > 1 && <PaginationControls />}
            </>
          )}
        </BlockStack>
      </Box>
    </AdminBlock>
  );
}
